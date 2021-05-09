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
//use paygw_liqpay\liqpay_helper;

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

        $config = (object)helper::get_gateway_configuration($component, $paymentarea, $itemid, 'liqpay');
        $sandbox = $config->environment == 'sandbox';

        $payable = payment_helper::get_payable($component, $paymentarea, $itemid);
        $currency = $payable->get_currency();

        // Add surcharge if there is any.
        $surcharge = helper::get_gateway_surcharge('liqpay');
        $amount = helper::get_rounded_cost($payable->get_amount(), $currency, $surcharge);

        $orderdetails = json_decode($orderdata, true);
        $localsign = base64_encode( sha1( $config->privatekey . $orderdetails['data'] . $config->privatekey, true ));
        $success = false;
        $message = '';

        if ($orderdetails) {
            // verification if signature does passed or not
            if (strcmp($orderdetails['signature'], $localsign) == 0) {
                if ((strlen($orderdetails['action']) > 0) && (strlen($orderdetails['status']) > 0)) {
                    if ((strcmp($orderdetails['action'], 'pay') == 0) && (strcmp($orderdetails['status'], 'success') == 0)) {
                        if ($orderdetails['amount'] == $amount && $orderdetails["currency"] == $currency) {
                            $success = true;
                            // Everything is correct. Let's give them what they paid for.
                            try {
                                $paymentid = payment_helper::save_payment($payable->get_account_id(), $component, $paymentarea,
                                    $itemid, (int) $USER->id, $amount, $currency, 'liqpay');
                                // date fixing
                                if (empty($orderdetails['create_date'])) {$orderdetails['create_date']= time();}
                                if (empty($orderdetails['end_date'])) {$orderdetails['end_date']= $orderdetails['create_date'];}
                                // Store LiqPay extra information.
                                $record = new \stdClass();
                                $record->paymentid         = $paymentid;                         // Moodle PG payemnt ID
                                $record->lp_orderid        = $orderdetails['liqpay_order_id'];   // LP order ID
                                $record->payment_id        = $orderdetails['payment_id'];        // LP transaction_id
                                $record->amount            = $orderdetails['amount'];            // price amount 
                                $record->currency          = $orderdetails['currency'];          // currency of price
                                $record->amount_credit     = $orderdetails['amount_credit'];     // reveived by seller (receiver)
                                $record->currency_credit   = $orderdetails['currency_credit'];   // currency of receiver's account
                                $record->commission_credit = $orderdetails['commission_credit']; // commission from receiver
                                $record->amount_debit      = $orderdetails['amount_debit'];      // payed by customer
                                $record->currency_debit    = $orderdetails['currency_debit'];    // currency of customer's payment
                                $record->commission_debit  = $orderdetails['commission_debit'];  // commission from customer
                                $record->acq_id            = $orderdetails['acq_id'];            // An Equirer ID
                                $record->end_date          = $orderdetails['end_date'];          // Transaction end date
                                $record->create_date       = $orderdetails['create_date'];       // Transaction create date
                                $record->action            = $orderdetails['action'];            // LP action type
                                $record->payment_status    = $orderdetails['status'];            // "success"
                                $record->payment_type      = $orderdetails['type'];              // payment type
                                $record->paytype           = $orderdetails['paytype']; // card - оплата картой, 
                                                                                    // liqpay - через кабинет liqpay,
                                                                                    // privat24 - через кабинет приват24, 
                                                                                    // masterpass - через кабинет masterpass, 
                                                                                    // moment_part - рассрочка, 
                                                                                    // cash - наличными, 
                                                                                    // invoice - счет на e-mail, 
                                                                                    // qr - сканирование qr-кода
                                // Transaction error code
                                $record->err_code          = !empty($orderdetails['err_code'])? $orderdetails['err_code'] : '';

                                $DB->insert_record('paygw_liqpay', $record);

                                payment_helper::deliver_order($component, $paymentarea, $itemid, $paymentid, (int) $USER->id);
                            } catch (\Exception $e) {
                                debugging('Exception while trying to process payment: ' . $e->getMessage(), DEBUG_DEVELOPER);
                                $success = false;
                                $message = get_string('internalerror', 'paygw_liqpay');
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
                    $message = get_string('paymentstatusincorrect', 'paygw_liqpay');
                }
            } else {
                // Invalid authorization - signature mismatch!
                $success = false;
                $message = get_string('signaturemismatch', 'paygw_liqpay');
            }
        } else {
            // Could not capture ligpaydata!
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
