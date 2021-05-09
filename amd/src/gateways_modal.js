// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This module is responsible for LiqPay content in the gateways modal.
 *
 * @module     paygw_liqpay/gateway_modal
 * @copyright  2020 Shamim Rezaie (PayPal) <shamim@moodle.com>
 * @copyright  2021 Andrii Semenets (LiqPay)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Repository from './repository';
import Templates from 'core/templates';
import Truncate from 'core/truncate';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import {get_string as getString} from 'core/str';

/**
 * Creates and shows a modal that contains a placeholder.
 *
 * @returns {Promise<Modal>}
 */
const showModalWithPlaceholder = async() => {
    //switchSdk();
    const modal = await ModalFactory.create({
        body: await Templates.render('paygw_liqpay/liqpay_widget_placeholder', {})
    });
    modal.show();
    return modal;
};

/**
 * Process the payment.
 *
 * @param {string} component Name of the component that the itemId belongs to
 * @param {string} paymentArea The area of the component that the itemId belongs to
 * @param {number} itemId An internal identifier that is used by the component
 * @param {string} description Description of the payment
 * @returns {Promise<string>}
 */
export const process = (component, paymentArea, itemId, description) => {
    return Promise.all([
        showModalWithPlaceholder(),
        Repository.getConfigForJs(component, paymentArea, itemId, description),
    ])
    .then(([modal, liqpayConfig]) => {
        modal.getRoot().on(ModalEvents.hidden, () => {
            // Destroy when hidden.
            modal.destroy();
        });

        return Promise.all([
            modal,
            liqpayConfig,
            switchSdk(),
        ]);
    })
    .then(([modal, liqpayConfig]) => {
        return new Promise(resolve => {
            //window.LiqPayCheckoutCallback = function() {
            LiqPayCheckout.init({
                data: liqpayConfig.lpencdata,
                signature: liqpayConfig.lpsignature,
                embedTo: "#liqpay_checkout",
                language: liqpayConfig.language,
                mode: "embed" // embed || popup
            }).on("liqpay.callback", function(data){
                console.log(data.status);
                console.log(data);
                console.log(JSON.stringify(data));
                console.log(typeof JSON.stringify(data));
                            modal.getRoot().on(ModalEvents.outsideClick, (e) => {
                                // Prevent closing the modal when clicking outside of it.
                                e.preventDefault();
                            });
                            modal.setBody(getString('authorising', 'paygw_liqpay'));
                            Repository.markTransactionComplete(component, paymentArea, itemId, JSON.stringify(data))
                            .then(res => {
                                modal.hide();
                                return res;
                            })
                            .then(resolve);
            }).on("liqpay.ready", function(data){
                // ready
            }).on("liqpay.close", function(data){
                // close
            });
            //};
        });
    })
    .then(res => {
        if (res.success) {
            return Promise.resolve(res.message);
        }

        return Promise.reject(res.message);
    });
};

/**
 * Unloads the previously loaded LiqPay JavaScript SDK, and loads a new one.
 *
 * @returns {Promise}
 */
const switchSdk = async() => {
    const sdkUrl = `https://static.liqpay.ua/libjs/checkout.js`;

    // Check to see if this file has already been loaded. If so just go straight to the func.
    if (switchSdk.currentlyloaded === sdkUrl) {
        return Promise.resolve();
    }

    const script = document.createElement('script');

    return new Promise(resolve => {
        if (script.readyState) {
            script.onreadystatechange = function() {
                if (this.readyState == 'complete' || this.readyState == 'loaded') {
                    this.onreadystatechange = null;
                    resolve();
                }
            };
        } else {
            script.onload = function() {
                resolve();
            };
        }

        script.setAttribute('src', sdkUrl);
        document.head.appendChild(script);

        switchSdk.currentlyloaded = sdkUrl;
    });
};

/**
 * Holds the full url of loaded PayPal JavaScript SDK.
 *
 * @static
 * @type {string}
 */
switchSdk.currentlyloaded = '';
