<?php
// This file is part of mod_exportquiz for Moodle - http://moodle.org/
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


defined('MOODLE_INTERNAL') || die();

$plugin->version  = 2015103001;
$plugin->release   = "2015-06-16";      // User-friendly version number.
$plugin->maturity  = MATURITY_STABLE;
$plugin->requires = 2014111000;         // Requires this Moodle version.
$plugin->cron     = 3600;               // Period for cron to check this module (secs).
$plugin->component = 'mod_exportquiz'; // Full name of the plugin (used for diagnostics).
