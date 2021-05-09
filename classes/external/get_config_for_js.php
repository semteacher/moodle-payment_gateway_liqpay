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
use external_single_structure;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

class get_config_for_js extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Component'),
            'paymentarea' => new external_value(PARAM_AREA, 'Payment area in the component'),
            'itemid' => new external_value(PARAM_INT, 'An identifier for payment area in the component'),
            'description' => new external_value(PARAM_TEXT, 'DEscroption of payment'),
        ]);
    }

    /**
     * Returns the config values required by the LiqPay JavaScript SDK.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @return string[]
     */
    public static function execute(string $component, string $paymentarea, int $itemid, string $description): array {
        global $USER;
        self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'description' => description,
        ]);

        $config = helper::get_gateway_configuration($component, $paymentarea, $itemid, 'liqpay');
        $payable = helper::get_payable($component, $paymentarea, $itemid);
        $surcharge = helper::get_gateway_surcharge('liqpay');

        if (in_array(current_language(), ['uk', 'en', 'ru']) ) {
            $liqpaylang = current_language();
        } else {
            $liqpaylang = 'en';
        }

        $lpencdata = base64_encode(json_encode([
                                    'version'        => '3',
                                    'public_key'      => $config['publickey'],
                                    'private_key'     => $config['privatekey'],
                                    'action'         => 'pay',
                                    'amount'         => helper::get_rounded_cost($payable->get_amount(), $payable->get_currency(), $surcharge),
                                    'currency'       => $payable->get_currency(),
                                    'description'    => $description,
                                    'order_id'       => "{$USER->id}-{$component}-{$itemid}-".time(),
                                    'language'       => $liqpaylang,        
        ]));
        $lpsignature = base64_encode( sha1( $config['privatekey'] . $lpencdata . $config['privatekey'], true ));

        return [
            'lpencdata' => $lpencdata,
            'lpsignature'=> $lpsignature,
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'lpencdata' => new external_value(PARAM_TEXT, 'Encoded data for LiqPay'),
            'lpsignature' => new external_value(PARAM_TEXT, 'Encoded signature for LiqPay'),
        ]);
    }
}
