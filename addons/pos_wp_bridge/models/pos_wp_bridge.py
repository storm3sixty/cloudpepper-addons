from urllib.parse import urljoin

import requests

from odoo import _, api, fields, models
from odoo.exceptions import UserError


class PosWpBridgeConfig(models.Model):
    _name = "pos.wp.bridge.config"
    _description = "WordPress Bridge Configuration"

    name = fields.Char(default="Main WordPress", required=True)
    wordpress_base_url = fields.Char(required=True)
    odoo_passcode = fields.Char(required=True)
    wc_consumer_key = fields.Char()
    wc_consumer_secret = fields.Char()
    active = fields.Boolean(default=True)
    last_sync_at = fields.Datetime(readonly=True)

    def _normalized_base_url(self):
        self.ensure_one()
        base = (self.wordpress_base_url or "").strip()
        if not base:
            raise UserError(_("WordPress Base URL is required."))
        return base if base.endswith("/") else f"{base}/"

    def action_test_bridge(self):
        for rec in self:
            base_url = rec._normalized_base_url()
            endpoint = urljoin(base_url, "wp-json/odoo-bridge/v1/ping")
            response = requests.get(
                endpoint,
                headers={"X-Odoo-Passcode": rec.odoo_passcode},
                timeout=20,
            )
            if response.status_code != 200:
                raise UserError(_("Bridge test failed (%s): %s") % (response.status_code, response.text))
        return {
            "type": "ir.actions.client",
            "tag": "display_notification",
            "params": {
                "title": _("Success"),
                "message": _("WordPress bridge connection succeeded."),
                "type": "success",
                "sticky": False,
            },
        }

    def action_pull_bookings(self):
        Booking = self.env["pos.wp.booking"].sudo()
        for rec in self:
            base_url = rec._normalized_base_url()
            endpoint = urljoin(base_url, "wp-json/odoo-bridge/v1/bookings")
            response = requests.get(
                endpoint,
                headers={"X-Odoo-Passcode": rec.odoo_passcode},
                timeout=30,
            )
            if response.status_code != 200:
                raise UserError(_("Failed to pull bookings (%s): %s") % (response.status_code, response.text))
            payload = response.json()
            for item in payload:
                Booking._upsert_from_wordpress(item, rec.id)

            rec.last_sync_at = fields.Datetime.now()
        return True

    def action_pull_orders(self):
        Order = self.env["pos.wp.order"].sudo()
        for rec in self:
            if not rec.wc_consumer_key or not rec.wc_consumer_secret:
                raise UserError(_("WooCommerce key and secret are required to pull orders."))

            base_url = rec._normalized_base_url()
            endpoint = urljoin(base_url, "wp-json/odoo-bridge/v1/orders")
            response = requests.get(
                endpoint,
                headers={"X-Odoo-Passcode": rec.odoo_passcode},
                auth=(rec.wc_consumer_key, rec.wc_consumer_secret),
                timeout=30,
            )
            if response.status_code != 200:
                raise UserError(_("Failed to pull orders (%s): %s") % (response.status_code, response.text))
            payload = response.json()
            for item in payload:
                Order._upsert_from_wordpress(item, rec.id)
            rec.last_sync_at = fields.Datetime.now()
        return True


class PosWpBooking(models.Model):
    _name = "pos.wp.booking"
    _description = "WordPress Booking"
    _order = "booking_datetime desc"

    config_id = fields.Many2one("pos.wp.bridge.config", required=True, ondelete="cascade")
    external_id = fields.Char(required=True, index=True)
    customer_name = fields.Char(required=True)
    customer_phone = fields.Char()
    customer_email = fields.Char()
    party_size = fields.Integer(default=1)
    booking_datetime = fields.Datetime(required=True)
    status = fields.Char(default="pending")
    notes = fields.Text()
    raw_payload = fields.Json()

    _sql_constraints = [
        ("pos_wp_booking_unique", "unique(config_id, external_id)", "Duplicate booking ID for this connector."),
    ]

    @api.model
    def _upsert_from_wordpress(self, payload, config_id):
        external_id = str(payload.get("id") or "")
        if not external_id:
            return False
        values = {
            "config_id": config_id,
            "external_id": external_id,
            "customer_name": payload.get("name") or _("Guest"),
            "customer_phone": payload.get("phone"),
            "customer_email": payload.get("email"),
            "party_size": int(payload.get("party_size") or 1),
            "booking_datetime": payload.get("datetime"),
            "status": payload.get("status") or "pending",
            "notes": payload.get("notes"),
            "raw_payload": payload,
        }
        record = self.search([("config_id", "=", config_id), ("external_id", "=", external_id)], limit=1)
        if record:
            record.write(values)
        else:
            record = self.create(values)

        self.env["bus.bus"]._sendone(
            "pos_wp_booking_channel",
            "wp_booking_created",
            {
                "id": record.id,
                "external_id": record.external_id,
                "name": record.customer_name,
                "party_size": record.party_size,
                "booking_datetime": record.booking_datetime.isoformat() if record.booking_datetime else None,
                "status": record.status,
            },
        )
        return record


class PosWpOrder(models.Model):
    _name = "pos.wp.order"
    _description = "WordPress/Woo Order"
    _order = "write_date desc"

    config_id = fields.Many2one("pos.wp.bridge.config", required=True, ondelete="cascade")
    external_id = fields.Char(required=True, index=True)
    customer_name = fields.Char()
    total_amount = fields.Float()
    currency = fields.Char()
    status = fields.Char()
    raw_payload = fields.Json()

    _sql_constraints = [
        ("pos_wp_order_unique", "unique(config_id, external_id)", "Duplicate order ID for this connector."),
    ]

    @api.model
    def _upsert_from_wordpress(self, payload, config_id):
        external_id = str(payload.get("id") or "")
        if not external_id:
            return False

        customer_name = payload.get("billing", {}).get("first_name", "")
        if payload.get("billing", {}).get("last_name"):
            customer_name = f"{customer_name} {payload['billing']['last_name']}".strip()

        values = {
            "config_id": config_id,
            "external_id": external_id,
            "customer_name": customer_name or payload.get("customer_name"),
            "total_amount": float(payload.get("total") or 0.0),
            "currency": payload.get("currency"),
            "status": payload.get("status"),
            "raw_payload": payload,
        }
        record = self.search([("config_id", "=", config_id), ("external_id", "=", external_id)], limit=1)
        if record:
            record.write(values)
        else:
            record = self.create(values)
        return record
