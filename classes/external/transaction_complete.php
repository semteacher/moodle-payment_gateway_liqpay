<?php
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
 * This class contains a list of webservice functions related to the LiqPay payment gateway.
 *
 * @package    paygw_liqpay
 * @copyright  2020 Shamim Rezaie (PayPal) <shamim@moodle.com>
 * @copyright  2021 Andrii Semenets (LiqPay)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace paygw_liqpay\external;

use core_payment\helper;
use external_api;
use external_function_parameters;
use external_value;
use core_payment\helper as payment_helper;
use paygw_liqpay\liqpay_helper;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

class transaction_complete extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'The component name'),
            'paymentarea' => new external_value(PARAM_AREA, 'Payment area in the component'),
            'itemid' => new external_value(PARAM_INT, 'The item id in the context of the component area'),
            'orderdata' => new external_value(PARAM_RAW, 'The order data coming back from LiqPay'),
        ]);
    }

    /**
     * Perform what needs to be done when a transaction is reported to be complete.
     * This function does not take cost as a parameter as we cannot rely on any provided value.
     *
     * @param string $component Name of the component that the itemid belongs to
     * @param string $paymentarea
     * @param int $itemid An internal identifier that is used by the component
     * @param string $orderid LiqPay order ID
     * @return array
     */
    public static function execute(string $component, string $paymentarea, int $itemid, string $orderdata): array {
        global $USER, $DB;

        self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'orderdata' => $orderdata,
        ]);
error_log(print_r($orderdata, true));
file_put_contents ('liqpayresponce.log',print_r($orderdata, true),FILE_APPEND);
        $config = (object)helper::get_gateway_configuration($component, $paymentarea, $itemid, 'liqpay');
        $sandbox = $config->environment == 'sandbox';

        $payable = payment_helper::get_payable($component, $paymentarea, $itemid);
        $currency = $payable->get_currency();

        // Add surcharge if there is any.
        $surcharge = helper::get_gateway_surcharge('liqpay');
        $amount = helper::get_rounded_cost($payable->get_amount(), $currency, $surcharge);

        $paypalhelper = new paypal_helper($config->publickey, $config->privatekey, $sandbox);
        $orderdetails = $paypalhelper->get_order_details($orderdata);

        $success = false;
        $message = '';

        if ($orderdetails) {
            if ($orderdetails['status'] == paypal_helper::ORDER_STATUS_APPROVED &&
                    $orderdetails['intent'] == paypal_helper::ORDER_INTENT_CAPTURE) {
                $item = $orderdetails['purchase_units'][0];
                if ($item['amount']['value'] == $amount && $item['amount']['currency_code'] == $currency) {
                    $capture = $paypalhelper->capture_order($orderdata);
                    if ($capture && $capture['status'] == paypal_helper::CAPTURE_STATUS_COMPLETED) {
                        $success = true;
                        // Everything is correct. Let's give them what they paid for.
                        try {
                            $paymentid = payment_helper::save_payment($payable->get_account_id(), $component, $paymentarea,
                                $itemid, (int) $USER->id, $amount, $currency, 'liqpay');

                            // Store LiqPay extra information.
                            $record = new \stdClass();
                            $record->paymentid = $paymentid;
                            $record->pp_orderid = $orderdata;

                            $DB->insert_record('paygw_liqpay', $record);

                            payment_helper::deliver_order($component, $paymentarea, $itemid, $paymentid, (int) $USER->id);
                        } catch (\Exception $e) {
                            debugging('Exception while trying to process payment: ' . $e->getMessage(), DEBUG_DEVELOPER);
                            $success = false;
                            $message = get_string('internalerror', 'paygw_liqpay');
                        }
                    } else {
                        $success = false;
                        $message = get_string('paymentnotcleared', 'paygw_liqpay');
                    }
                } else {
                    $success = false;
                    $message = get_string('amountmismatch', 'paygw_liqpay');
                }
            } else {
                $success = false;
                $message = get_string('paymentnotcleared', 'paygw_liqpay');
            }
        } else {
            // Could not capture authorization!
            $success = false;
            $message = get_string('cannotfetchorderdatails', 'paygw_liqpay');
        }

        return [
            'success' => $success,
            'message' => $message,
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_function_parameters
     */
    public static function execute_returns() {
        return new external_function_parameters([
            'success' => new external_value(PARAM_BOOL, 'Whether everything was successful or not.'),
            'message' => new external_value(PARAM_RAW, 'Message (usually the error message).'),
        ]);
    }
}
