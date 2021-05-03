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
 * External functions and service definitions for the PayPal payment gateway plugin.
 *
 * @package    paygw_liqpay
 * @copyright  2020 Shamim Rezaie (PayPal) <shamim@moodle.com>
 * @copyright  2021 Andrii Semenets (LiqPay)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'paygw_liqpay_get_config_for_js' => [
        'classname'   => 'paygw_liqpay\external\get_config_for_js',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Returns the LiqPay configuration settings to be used in js',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'paygw_liqpay_create_transaction_complete' => [
        'classname'   => 'paygw_liqpay\external\transaction_complete',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Takes care of what needs to be done when a LiqPay transaction comes back as complete.',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => false,
    ],
];
