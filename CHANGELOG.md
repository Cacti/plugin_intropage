# Changelog


--- develop ---

* feature#259: improve all panels

* feature: Add red/yellow/green indicators to bussiest panels

* issue#272: Improve Intropage logging in two places to increase the ability to diagnose issues

* New event highlighting system

--- 4.0.3 ---

* feature#226: Panel Devices - add what devices are down and fail date

* feature#228: Add Busiest panel - top utilized ports

* feature#350: Selectable number of lines in panel

* feature: Better trends - line graphs for hosts and tholds

* issue#235: Bussiest panels do not respect permissions

* issue#237: Fix PHP 8.x warning - trim function

* issue#243: Fix PopUp - Error message of missing Intropage permissions 

--- 4.0.2 ---

* issue: Clear warnings issued on 'busiest' panel when interface traffic is zero

* issue: Ensure that panels with links use callbacks

* issue: Vertical scroll bar was not appearing on some themes

* issue: Fix javascript for displaying topX graph

* issue#220: Fix php warning config_form does not exists

* feature#208: Add select all to permissions tab

* feature: Add DNS resolving check

* feature#203: Add dashboard sharing

* feature: Change default permission - allow user level panels for new user


--- 4.0.1 ---

* issue#181: Fix autorefresh

* issue#191: Adding panels does not work as expected

* issue#192: Human Readable calculations can result in offset errors

* issue#194: Fix busiest panel display after dsstats reset

* feature#180: Add Mb/MB option

* feature#182: Selectable number of panels per line

* feature#188: Poller output items graph panel


--- 4.0.0 ---

* issue: Attempt to cleanup more memory leaks

* issue: Properly keep panels that have plugin requirements from being
  selectable

* issue: Don't run the polling functions for the panel if the required plugins
  are not enabled

* issue: Trend function in panellib analyze requires thold but was not indicated

* issue#143: Swapping out c3 for billboard

* issue#157: No panel can be added after install Intropage

* issue#159: Fix missing fav_graph permission

* issue#162: When user is not logged in, wrong panel data key can cause errors
  to appear

* issue#165: Fix PHP warning division by zero

* issue#166: Division by zero error

* issue#167: Duplicate attempts to insert user panel settings when no panels
  allocated

* issue#168: Fix typo in variable name

* issue#169: Shortening the panel name Favourite graph

* issue#171: Better TOP/Bottom5 panel styles

* issue#172: Fix PHP notice when favourite graph exists and timespan changes

* issue#177: Fix PHP error during update from 3.x

* issue#178: Fix missing panel name in log message

* feature#161: Add busiest panels (DS Stats)

* feature#164: Line graph add zoom, pie graph add tooltip percentage

* feature#174: New panel for DSstats (all/null)


--- 3.0.3 ---

* Add user selectable timespan for timeseries charts

* Convert Charting from Chart.js to C3.js

* Add Trend Intervals to gathering sampling data frequency separate from
  rendering

* Add Windows to Timeseries charts and provide peak and average values for some

* Allow Poller Runtimes to have fractional values stored in trend table

* More fine tuning for mobile devices

* When dropping a panel, update panel dropdowns to show that it can be added
  again

* Force chart resize on more actions inside the browser, like dropping a panel

* Update Line chart API to allow the user to specify the left or right axes by
  series name

* Add some dividers between action types in Actions... dropdown

* Fix GUI issues where Administrators lost their ability to set refresh
  intervals for Admin panels

* Fix database issue where dropping a panel did not remove the data record for
  user level panels

* Fix some issues where undefined variable errors could be logged

* Bump minimum required version to Cacti 1.2.17

* Force Intropage to have load order 1 always so that it can inspect key tables

* Add support for Midwinter theme

* Support Poller intervals down to every 10 seconds

* Make panel definitions trend interval aware

* Convert many details pages to table rendering

* Block page refresh when a dialog is active


--- 3.0.2 ---

* Reduce the width of some titles to conserve space

* Reduce the length of hostnames to conserve space

* Make switching between tab and console view instantaneous

* CSS Corrections in modern theme to properly place content

* Missing some critical includes

* Correct issues with alarm coloring rule adherence

* Adjust boost settings to work with latest and legacy boost

* Fix a number of SQL errors

* Support Midwinter Theme

* QA on a number of reworked panels

* Remove inline styles where possible

* issue#133 - Add favourite graph is not possible in 3.0.x

* issue#138 - Copy text from panel is broken


--- 3.0.1 ---

* Correct syntax error in functions.php


--- 3.0.0 ---

* Redesign for sustainability and extensibility

* New 'Panellib' design to allow for easy customization

* Revamp permission system to allow easier extensibility

* Separate panel libraries into classes

* Add additional core functions to reduce code duplication

* New table columns

* Allow Panels to not allow 'Forced Refresh'

* Fix erratic behavior when moving panels on page

* Better support for mobile browsers

* Support panel widths


--- 2.0.6 ---

* Add dashboard names

* Add panel config

* User can rename dashboards

* Fix favourite graphs with zoomed/custom timespan


--- 2.0.5 ---

* Add panel webseer plugin

* User can set red/yellow/green alarm for checks

	
--- 2.0.4 ---

* Better work with panels (#115)

* Compatibility with RHEL7/old php (#115)
		

--- 2.0.3 ---

* Poller speed up

* Fix a lot of small bugs


--- 2.0.2 ---

* All panels with user permission

* Fix public/private community warning (#110)


--- 2.0.1 ---

* Fix wrong path (#100)

* Fix remove permission (#102)


--- 2.0 ---

* Fix few bugs


--- 1.9.2 ---

* Move user auth to own table


--- 1.9 ---

* Speed-up

* Multiple dashboards

* Third party panels

* New gathering data in separated poller process


--- 1.8.4 ---

* Speed-up

* Fix automatic refresh after poller finish


--- 1.8.3 ---

* Add logonly tholds

* Add DB check disable option

* Add NTP server check (domain name or IP)

* Add automatic refresh after poller finish

* Detail to the new window

* Fix panel reload - Cigamit

* Fix save panel order

* Fix Admin alert panel - not working with ajax

* Fix maint panel - not working with ajax

* A lot of optimalization, speed-up


--- 1.8.2 ---

* Add panel with worst polling time

* Add panel with worst failed/total polling cycles

* Add db check week and month interval

* Add DS - bad indexes

* Add remark for disable original console dashboard

* Fix few bugs with permissions, more users, ...


--- 1.8.1 ---

* Add test for poller_output_table

* Add check for thold notigy global list only

* Add test Cacti and poller version

* Load/reload of panels now via ajax callbacks

* Fix javascript/jquery error (MSIE11 fix)

* Fix problem with host permission

* Move Analyse DB and NTP to poller and run periodically

* Better themes support

        
--- 1.8 ---

* Add check for cacti and spine version

* Add panel for Mactrack plugin (again)

* Add panel for Top5 Mactrack sites

* Add panel Maintenace alert

* Fix number of errors in snmp default community test

* Add test for extrems, trends - is thold plugin installed and running?

* Fix PHP noticed if thold isnt installed

* Fix Top5 display if no data

* Change error/warning in analyse tree/host/graph

* Better themes support


--- 1.7 ---

* Add Awesome icons (close, reload, show detail,...)

* Add user permissions

* Add default snmp community (public/private) check

* Improve favourite graphs - you can set more than 2 graphs

* Improve ntp - add time difference

* Fix blank login page if intropage in console is default page

* Fix show/hide detail

* Fix db check - Ok tables are reported as damaged if check level is "Changed"

* Fix drag and drop

* Fix sorting panels


--- 1.6 ---

* Add user setting directly on intropage (close/add panel, autorefresh, ...)

* Add permissions (admin can enable/disable panel for all users)

* Add Favourite graphs panel

* Add option for cahnge default page for users without console

* Add detail panel of up/down/recovering hosts

* Fix error and warnings count in Analyse log panel

* Fix ajax double displaying


--- 1.5.1 ---

* Fix top5 - add device disabled test

* Fix display in IE10 and IE11

* Fix display in all themes


--- 1.5 ---

* Add Boost statistics panel

* Add Orphaned DS to analyze tree/host/graph

* Add Last thold events panel

* Add Gray panel for panels without tests and alarms

* Add save panel order (drag and drop)


--- 1.4 ---

* Add 24 hour extrem panel

* Ajax reload

* Ajax view/hide details

* Fix analyse log messages

* Join panels analyse log and analyse log size


--- 1.3 ---

* Add drag and drop panel

* Add monitor plugin check again

* Fix poller graph - incorect times

* Fix install script

* Fix a lot of typo/small errors


--- 1.2 ---

* Add poller graph for more pollers

* Add poller statistics for all pollers

* Add CPU load graph instead of text information

* Add yellow alarm for ping > 1000ms or < 75% availability

* Add last poller time to poller info

* Add plugin monitor check

* Add links for setting for users without console

* Fix host, thold count (wrong permission)

* Fix DB check level

* Fix displaying details

* Fix panel size

* Fix alarm in analyse DB

* CSS optimalization

	
--- 1.1 ---

* CSS optimalization for all themes

* Add graph colors

* Fix tab image in classic theme


--- 1.0 ---

* Completely new design and function - Dashboard

* Add automatic refresh page

* Add CPU monitoring (linux/unix)

* Add trends

* Add poller stats and info

* Add mysql db connection check

* Add checks for IP and description duplicity (thank you BigAl101)

	
--- 0.9 ---

* Rewrite all for Cacti 1.0.x (thank you Earendil!)


--- 0.8 ---

* Add set default setting after plugin install

* Add few settings (host without graph, host without tree, ...)

* Add Subtree name in "Devices in more then one tree" (thank you, Hipska)

* Add debug option

* Add db check level

* Fix warning and notices (thank you, Ugo Guerra)

* Fix thold graph - triggered, breached (Thank you, Hipska)

* Fix last x lines log lines (thank you, Ugo Guerra)


--- 0.7 ---

* Add number rounding

* Add switch for default page setting for users without console access

* Add layout Best fit

* Fix layout

* Fix database check - memory tables cause false warnings

* Fix redirect function

* Fix return default page after unistall plugin

* Fix displaying links if user hasn't right

* Redesign Pie graphs (author: Trevor Leadley)


--- 0.6 ---

* Add separated Tab for plugin

* Add Cacti user rights (limited user cannot see all statistics but statistics
  of authorized equipment)

* Add cacti database check

* Add more information in logs

* Add Search FATAL errors in log

* Fix NTP function (infinite loops if wrong ntp server is used)

* Redesign Pie graphs (author: Trevor Leadley)

* Redesign (author: Tomas Macek)


--- 0.5 ---

* Change time check (now via NTP)

* Add settings to Console -> Settings -> Intropage

* Add Mactrack

* Add more pie graphs

* Redesign


--- 0.4 ---

* Add pie graphs (need PHP GD library)

* Add checks for:

  - device with the same name

  - device without tree

  - device more times in tree

  - device without graphs

* Add Top 5:

  - the worst ping response

  - the lowest availability

* Fix php notices and warnings

* Redesign


--- 0.3 ---

* Add OS and poller information

* Add time check

* Add control poller duration

* Add icons


--- 0.2 ---

* Fix error - number of all tholds


--- 0.1 ---

* Beginning

-----------------------------------------------
Copyright (c) 2004-2023 - The Cacti Group, Inc.
