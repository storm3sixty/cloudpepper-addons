/** @odoo-module **/

import { PosStore } from "@point_of_sale/app/store/pos_store";
import { patch } from "@web/core/utils/patch";

patch(PosStore.prototype, {
    async setup() {
        await super.setup(...arguments);
        this.wpBookings = [];
        this.wpBookingCounter = 0;

        const busService = this.env.services.bus_service;
        busService.addChannel("pos_wp_booking_channel");
        busService.addEventListener("notification", ({ detail }) => {
            for (const message of detail) {
                const [channel, type, payload] = message;
                if (channel === "pos_wp_booking_channel" && type === "wp_booking_created") {
                    this.wpBookings.unshift(payload);
                    this.wpBookingCounter += 1;
                    this.env.services.notification.add(
                        `New website booking: ${payload.name} (${payload.party_size} pax)`,
                        {
                            title: "Table Reservation",
                            type: "success",
                        }
                    );
                }
            }
        });
    },
});
