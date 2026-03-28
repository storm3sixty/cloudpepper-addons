/** @odoo-module **/

import { PosStore } from "@point_of_sale/app/store/pos_store";
import { patch } from "@web/core/utils/patch";

function beep(duration = 250, frequency = 1000) {
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextClass) {
        return;
    }
    const context = new AudioContextClass();
    const oscillator = context.createOscillator();
    const gainNode = context.createGain();

    oscillator.type = "square";
    oscillator.frequency.value = frequency;
    oscillator.connect(gainNode);
    gainNode.connect(context.destination);
    gainNode.gain.value = 0.15;

    oscillator.start();
    setTimeout(() => {
        oscillator.stop();
        context.close();
    }, duration);
}

function printOrderPayload(payload) {
    const lines = (payload.ordered_items || "").split("\n").filter(Boolean);
    const html = `
        <html><body style="font-family: monospace; font-size: 12px;">
            <h3>Website Order #${payload.external_id || ""}</h3>
            <div><strong>Customer:</strong> ${payload.name || ""}</div>
            <div><strong>Address:</strong> ${payload.customer_address || ""}</div>
            <div><strong>Notes:</strong> ${payload.order_notes || ""}</div>
            <hr />
            <div><strong>Items</strong></div>
            ${lines.map((line) => `<div>${line}</div>`).join("")}
            <hr />
            <div><strong>Total:</strong> ${payload.total_amount || 0} ${payload.currency || ""}</div>
        </body></html>
    `;

    const receiptWindow = window.open("", "_blank", "width=360,height=600");
    if (!receiptWindow) {
        return;
    }
    receiptWindow.document.write(html);
    receiptWindow.document.close();
    receiptWindow.focus();
    receiptWindow.print();
    receiptWindow.close();
}

patch(PosStore.prototype, {
    async setup() {
        await super.setup(...arguments);
        this.wpBookings = [];
        this.wpOrders = [];
        this.wpBookingCounter = 0;
        this.wpOrderCounter = 0;

        const busService = this.env.services.bus_service;
        busService.addChannel("pos_wp_booking_channel");
        busService.addChannel("pos_wp_order_channel");

        busService.addEventListener("notification", ({ detail }) => {
            for (const message of detail) {
                const [channel, type, payload] = message;

                if (channel === "pos_wp_booking_channel" && type === "wp_booking_created") {
                    this.wpBookings.unshift(payload);
                    this.wpBookingCounter += 1;
                    beep(300, 1200);
                    this.env.services.notification.add(
                        `New booking: ${payload.name} (${payload.party_size} pax)`,
                        {
                            title: "Website Table Reservation",
                            type: "success",
                            sticky: true,
                        }
                    );
                }

                if (channel === "pos_wp_order_channel" && type === "wp_order_created") {
                    this.wpOrders.unshift(payload);
                    this.wpOrderCounter += 1;
                    beep(450, 900);
                    this.env.services.notification.add(
                        `New website order: ${payload.name || "Guest"}`,
                        {
                            title: "Website Order",
                            type: "warning",
                            sticky: true,
                        }
                    );
                    printOrderPayload(payload);
                }
            }
        });
    },
});
