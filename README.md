# plugin_intropage

## Intropage/Dashboard plugin for Cacti
Plugin displays usefull information and favourite graphs on console screen or separated tab:
* trends
* host graph (total, down, ...)
* poller statistics
* thresholds (all, trigged, ...)
* logs analyze
* worst ping and availability
* ...

## Author 
Petr Macek (petr.macek@kostax.cz)

## Screenshot
![Screenshot](https://user-images.githubusercontent.com/26485719/41935583-78f73d32-798a-11e8-83f4-768d2e454a79.png)


## Installation
Copy directory intropage to plugins directory <br/>
Check file permission (Linux/unix - readable for www server) <br/>
Enable plugin (Console -> Plugin management) <br/>
Configure plugin (Console -> Settings -> Intropage tab) <br/>
You can set Intropage as first page (Console -> User managemnt -> user -> Login Options)  <br/>
    
## Upgrade
Delete old files <br/>
Copy new files <br/>
Check file permission (Linux/unix - readable for www server) <br/>
Disable and deinstall old version (Console -> Plugin management)  <br/>
Install and enable new version (Console -> Plugin management)  <br/>
Configure plugin (Console -> Settings -> Intropage tab) <br/>

## Configuration
Console -> users -> choose users -> Intropage options and panels (admin) <br/>
Settings -> Intropage (admin) <br/>
On intropage page -> below form (user) <br/>
    
## Possible Bugs?
If you find a problem, let me know via github or https://forums.cacti.net/viewtopic.php?f=5&t=51920 

## Thanks
Tomas Macek, Peter Michael Calum, Trevor Leadley, Earendil 

## Changelog
	1.8 ---
	Add check for cacti and spine version
	Fix number of errors in snmp defaul community test

	1.7 ---
	Add Awesome icons (close, reload, show detail,...)
	Add user permissions 
	Add default snmp community (public/private) check
	Improve favourite graphs - you can set more than 2 graphs
	Improve ntp - add time difference
	Fix blank login page if intropage in console is default page
	Fix show/hide detail
	Fix db check - Ok tables are reported as damaged if check level is "Changed"
	Fix drag and drop
	Fix sorting panels

	1.6 ---
	Add user setting directly on intropage (close/add panel, autorefresh, ...)
	Add permissions (admin can enable/disable panel for all users)
	Add Favourite graphs panel
	Add option for cahnge default page for users without console
	Add detail panel of up/down/recovering hosts
	Fix error and warnings count in Analyse log panel
	Fix ajax double displaying

	1.5.1 ---
	Fix top5 - add device disabled test
	Fix display in IE10 and IE11
	Fix display in all themes

	1.5 ---
	Add Boost statistics panel
	Add Orphaned DS to analyze tree/host/graph
	Add Last thold events panel
	Add Grey panel for panels without tests and alarms
	Add save panel order (drag and drop)

	1.4 ---
	Add 24 hour extrem panel
	Ajax reload
	Ajax view/hide details
	Fix analyse log messages
	Join panels analyse log and analyse log size

	1.3 ---
	Add drag and drop panel
	Add monitor plugin check again
	Fix poller graph - incorect times
	Fix install script 
	Fix a lot of typo/small errors

	1.2 ---
	Add poller graph for more pollers
	Add poller statistics for all pollers
	Add CPU load graph instead of text information
	Add yellow alarm for ping > 1000ms or < 75% availability
	Add last poller time to poller info
	Add plugin monitor check
	Add links for setting for users without console
	Fix host, thold count (wrong permission)
	Fix DB check level
	Fix displaying details
	Fix panel size
	Fix alarm in analyse DB
	CSS optimalization
	
	1.1 ---
	CSS optimalization for all themes
	Add graph colors
	Fix tab image in classic theme

	1.0 ---
	Completely new design and function - Dashboard
	Add automatic refresh page
	Add CPU monitoring (linux/unix)
	Add trends
	Add poller stats and info
	Add mysql db connection check
	Add checks for IP and description duplicity (thank you BigAl101)
	
	0.9 ---
	Rewrite all for Cacti 1.0.x (thank  you Earendil!)

        0.8 ---
	Add set default setting after plugin install
	Add few settings (host without graph, host without tree, ...)
	Add Subtree  name in "Devices in more then one tree" (thank you, Hipska)
	Add debug option
	Add db check level
	Fix warning and notices (thank you, Ugo Guerra)
	    Fix thold graph - triggered, breached (Thank you, Hipska)
	    Fix last x lines log lines (thank you, Ugo Guerra)
        0.7 ---
	Add number rounding
	Add switch for default page setting for users without console access
	Add layout Best fit
	Fix layout 
	Fix database check - memory tables cause false warnings
	Fix redirect function
	Fix return default page after unistall plugin
	Fix displaying links if user hasn't right
	Redesign Pie graphs (author: Trevor Leadley)
        0.6 ---
	Add separated Tab for plugin
	Add Cacti user rights (limited user cannot see all statistics but statistics of authorized equipment)
	Add cacti database check
	Add more information in logs
	Add Search FATAL errors in log  
	Fix NTP function (infinite loops if wrong ntp server is used)
	Redesign Pie graphs (author: Trevor Leadley)
	Redesign (author: Tomas Macek)
        0.5 ---
	Change time check (now via NTP)
	Add settings to Console -> Settings -> Intropage
	Add Mactrack 
	Add more pie graphs
	Redesign
        0.4 ---
	Add pie graphs (need PHP GD library)
	Add checks for:
	- device with the same name
	- device without tree
	- device more times in tree
	- device without graphs
	Add Top 5:
	- the worst ping response
	- the lowest availability
	Fix php notices and warnings
	Redesign
        0.3 ---
	Add OS and poller information
	Add time check
	Add control poller duration
	Add icons
    --- 0.2 ---
	Fix error - number of all tholds
    --- 0.1 ---
	Beginning



