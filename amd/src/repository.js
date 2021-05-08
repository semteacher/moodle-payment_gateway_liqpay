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
 * LiqPay repository module to encapsulate all of the AJAX requests that can be sent for LiqPay.
 *
 * @module     paygw_liqpay/repository
 * @package    paygw_liqpay
 * @copyright  2020 Shamim Rezaie (LiqPay) <shamim@moodle.com>
 * @copyright  2021 Andrii Semenets (LiqPay)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Return the LiqPay JavaScript SDK URL.
 *
 * @param {string} component Name of the component that the itemId belongs to
 * @param {string} paymentArea The area of the component that the itemId belongs to
 * @param {number} itemId An internal identifier that is used by the component
 * @returns {Promise<{publickey: string, privatekey: string, cost: number, currency: string}>}
 */
export const getConfigForJs = (component, paymentArea, itemId, description) => {
    const request = {
        methodname: 'paygw_liqpay_get_config_for_js',
        args: {
            component,
            paymentarea: paymentArea,
            itemid: itemId,
            description,
        },
    };

    return Ajax.call([request])[0];
};

/**
 * Call server to validate and capture payment for order.
 *
 * @param {string} component Name of the component that the itemId belongs to
 * @param {string} paymentArea The area of the component that the itemId belongs to
 * @param {number} itemId An internal identifier that is used by the component
 * @param {string} orderId The order id coming back from LiqPay
 * @returns {*}
 */
export const markTransactionComplete = (component, paymentArea, itemId, orderId) => {
    const request = {
        methodname: 'paygw_liqpay_create_transaction_complete',
        args: {
            component,
            paymentarea: paymentArea,
            itemid: itemId,
            orderid: orderId,
        },
    };

    return Ajax.call([request])[0];
};
