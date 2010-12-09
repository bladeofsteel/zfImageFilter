#!/usr/bin/env php
<?php
/**
 * Mail logger
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @copyright   Copyright (c) 2010 Oleg Lobach <oleg@lobach.info>
 * @license     http://www.gnu.org/licenses/gpl-3.0.html (GPLv3)
 * @author      Oleg Lobach <oleg@lobach.info>
 */

define('LOG_PATH', '/var/log/mail/');

$contents = file_get_contents('php://stdin');
if (preg_match('/^To:(.*)/', $contents, $match)) {
    $recepient = trim($match[1]);
} else {
    $recepient = '';
}

$filename = sprintf('%s_%s_%d.eml', date('Y-m-d-H-i-s'), $recepient, rand(100, 999));
file_put_contents(LOG_PATH . $filename, $contents, FILE_APPEND | LOCK_EX);