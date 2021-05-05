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
 * Contains class for PayPal payment gateway.
 *
 * @package    paygw_liqpay
 * @copyright  2021 Andrii Semenets (LiqPay)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_liqpay;

/**
 * The gateway class for PayPal payment gateway.
 *
 * @copyright  2019 Shamim Rezaie (Pay Pal)<shamim@moodle.com>
 * @copyright  2021 Andrii Semenets (LiqPay)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gateway extends \core_payment\gateway {
    public static function get_supported_currencies(): array {
        // See https://developer.paypal.com/docs/api/reference/currency-codes/,
        // 3-character ISO-4217: https://en.wikipedia.org/wiki/ISO_4217#Active_codes.
        return [
            'UAH', 'EUR', 'USD', 'RUB'
        ];
    }

    /**
     * Configuration form for the gateway instance
     *
     * Use $form->get_mform() to access the \MoodleQuickForm instance
     *
     * @param \core_payment\form\account_gateway $form
     */
    public static function add_configuration_to_gateway_form(\core_payment\form\account_gateway $form): void {
        $mform = $form->get_mform();

        $mform->addElement('text', 'publickey', get_string('publickey', 'paygw_liqpay'));
        $mform->setType('publickey', PARAM_TEXT);
        $mform->addHelpButton('publickey', 'publickey', 'paygw_liqpay');

        $mform->addElement('text', 'privatekey', get_string('privatekey', 'paygw_liqpay'));
        $mform->setType('privatekey', PARAM_TEXT);
        $mform->addHelpButton('privatekey', 'privatekey', 'paygw_liqpay');

        $options = [
            'live' => get_string('live', 'paygw_liqpay'),
            'sandbox'  => get_string('sandbox', 'paygw_liqpay'),
        ];

        $mform->addElement('select', 'environment', get_string('environment', 'paygw_liqpay'), $options);
        $mform->addHelpButton('environment', 'environment', 'paygw_liqpay');
    }

    /**
     * Validates the gateway configuration form.
     *
     * @param \core_payment\form\account_gateway $form
     * @param \stdClass $data
     * @param array $files
     * @param array $errors form errors (passed by reference)
     */
    public static function validate_gateway_form(\core_payment\form\account_gateway $form,
                                                 \stdClass $data, array $files, array &$errors): void {
        if ($data->enabled &&
                (empty($data->publickey) || empty($data->privatekey))) {
            $errors['enabled'] = get_string('gatewaycannotbeenabled', 'payment');
        }
    }
}
