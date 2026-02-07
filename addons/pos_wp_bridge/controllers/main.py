import hmac
import hashlib
import json

from odoo import http
from odoo.http import request


class PosWpBridgeWebhookController(http.Controller):
    @http.route("/pos_wp_bridge/webhook", type="http", auth="none", methods=["POST"], csrf=False)
    def receive_webhook(self, **kwargs):
        raw = request.httprequest.get_data() or b"{}"
        payload = json.loads(raw.decode("utf-8"))

        passcode = request.httprequest.headers.get("X-Odoo-Passcode", "")
        signature = request.httprequest.headers.get("X-WP-Signature", "")

        config = request.env["pos.wp.bridge.config"].sudo().search([
            ("odoo_passcode", "=", passcode),
            ("active", "=", True),
        ], limit=1)
        if not config:
            return request.make_response("Invalid passcode", status=401)

        expected = hmac.new(config.odoo_passcode.encode("utf-8"), raw, hashlib.sha256).hexdigest()
        if signature and not hmac.compare_digest(expected, signature):
            return request.make_response("Invalid signature", status=401)

        model = payload.get("type")
        if model == "booking":
            request.env["pos.wp.booking"].sudo()._upsert_from_wordpress(payload.get("data", {}), config.id)
        elif model == "order":
            request.env["pos.wp.order"].sudo()._upsert_from_wordpress(payload.get("data", {}), config.id)
        else:
            return request.make_response("Unsupported type", status=400)

        return request.make_response("ok", status=200)
