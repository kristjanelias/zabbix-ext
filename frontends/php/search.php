<?php
/*
** Zabbix
** Copyright (C) 2001-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/html.inc.php';

$page['title'] = _('Search');
$page['file'] = 'search.php';
$page['hist_arg'] = array();
$page['scripts'] = array('class.pmaster.js');

$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR				TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'type'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),		NULL),
		'search'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,			NULL),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favref'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'favcnt'=>		array(T_ZBX_INT, O_OPT,	null,	null,			NULL),
		'favaction'=>	array(T_ZBX_STR, O_OPT, P_ACT, 	IN("'flop','refresh'"), null),
		'favstate'=>	array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favaction})&&("flop"=={favaction})'),
	);

	check_fields($fields);

// ACTION /////////////////////////////////////////////////////////////////////////////
	if(isset($_REQUEST['favobj'])){
		$_REQUEST['pmasterid'] = get_request('pmasterid','mainpage');

		if('hat' == $_REQUEST['favobj']){
			if('flop' == $_REQUEST['favaction']){
				CProfile::update('web.search.hats.'.$_REQUEST['favref'].'.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
			}
			else if('refresh' == $_REQUEST['favaction']){
				switch($_REQUEST['favref']){
				}
			}
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		require_once dirname(__FILE__).'/include/page_footer.php';
		exit();
	}
?>
<?php

	$admin = uint_in_array($USER_DETAILS['type'], array(USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN));
	$rows_per_page = $USER_DETAILS['rows_per_page'];

	$searchWidget = new CWidget('search_wdgt');

	$search = get_request('search', '');

// Header
	if(zbx_empty($search)){
		$search = _('Search pattern is empty');
	}
	$searchWidget->setClass('header');
	$searchWidget->addHeader(array(_('SEARCH').': ',bold($search)), SPACE);

// FIND Hosts
	$params = array(
		'nodeids'=> get_current_nodeid(true),
		'search' => array(
			'name' => $search,
			'dns' => $search,
			'ip' => $search
		),
		'limit' => $rows_per_page,
		'selectGroups' => API_OUTPUT_EXTEND,
		'selectInterfaces' => API_OUTPUT_EXTEND,
		'selectItems' => API_OUTPUT_COUNT,
		'selectTriggers' => API_OUTPUT_COUNT,
		'selectGraphs' => API_OUTPUT_COUNT,
		'selectApplications' => API_OUTPUT_COUNT,
		'selectScreens' => API_OUTPUT_COUNT,
		'output' => array('name','status'),
		'searchByAny' => true
	);
	$db_hosts = API::Host()->get($params);

	order_result($db_hosts, 'name');

	$hosts = selectByPattern($db_hosts, 'name', $search, $rows_per_page);
	$hostids = zbx_objectValues($hosts, 'hostid');

	$params = array(
		'nodeids'=> get_current_nodeid(true),
		'hostids' => $hostids,
		'editable' => 1
	);
	$rw_hosts = API::Host()->get($params);
	$rw_hosts = zbx_toHash($rw_hosts,'hostid');

	$params = array(
		'nodeids'=> get_current_nodeid(true),
		'search' => array(
			'name' => $search,
			'dns' => $search,
			'ip' => $search
		),
		'countOutput' => 1,
		'searchByAny' => true
	);

	$overalCount = API::Host()->get($params);
	$viewCount = count($hosts);

	$header = array(
		ZBX_DISTRIBUTED ? new CCol(_('Node')) : null,
		new CCol(_('Hosts')),
		new CCol(_('IP')),
		new CCol(_('DNS')),
		new CCol(_('Latest data')),
		new CCol(_('Triggers')),
		new CCol(_('Events')),
		new CCol(_('Screens')),
		new CCol(_('Applications')),
		new CCol(_('Items')),
		new CCol(_('Triggers')),
		new CCol(_('Graphs')),
	);

	$table  = new CTableInfo();
	$table->setHeader($header);

	foreach($hosts as $hnum => $host){
		$hostid = $host['hostid'];

		$interface = reset($host['interfaces']);
		$host['ip'] = $interface['ip'];
		$host['dns'] = $interface['dns'];
		$host['port'] = $interface['port'];

		switch($host['status']){
			case HOST_STATUS_NOT_MONITORED:
				$style = 'on';
			break;
			default:
				$style = null;
			break;
		}


		$group = reset($host['groups']);
		$link = 'groupid='.$group['groupid'].'&hostid='.$hostid.'&switch_node='.id2nodeid($hostid);

		$caption = make_decoration($host['name'], $search);

		if (isset($rw_hosts[$hostid])) {
			$host_link = new CLink($caption, 'hosts.php?form=update&'.$link, $style);
			$applications_link = array(new CLink(_('Applications'), 'applications.php?'.$link), ' ('.$host['applications'].')');
			$items_link = array(new CLink(_('Items'), 'items.php?filter_set=1&'.$link), ' ('.$host['items'].')');
			$triggers_link = array(new CLink(_('Triggers'), 'triggers.php?'.$link), ' ('.$host['triggers'].')');
			$graphs_link = array(new CLink(_('Graphs'), 'graphs.php?'.$link), ' ('.$host['graphs'].')');
		}
		else {
			$host_link = new CSpan($caption, $style);
			$applications_link = array(new CSpan(_('Applications'), 'unknown'), ' ('.$host['applications'].')');
			$items_link = array(new CSpan(_('Items'), 'unknown'), ' ('.$host['items'].')');
			$triggers_link = array(new CSpan(_('Triggers'), 'unknown'), ' ('.$host['triggers'].')');
			$graphs_link = array(new CSpan(_('Graphs'), 'unknown'), ' ('.$host['graphs'].')');
		}

		if(!$admin){
			$host_link = new CSpan($caption, $style);
		}

		$hostip = make_decoration($host['ip'], $search);
		$hostdns = make_decoration($host['dns'], $search);

		$table->addRow(array(
			get_node_name_by_elid($hostid, true),
			$host_link,
			$hostip,
			$hostdns,
			new CLink(_('Latest data'), 'latest.php?'.$link),
			new CLink(_('Triggers'), 'tr_status.php?'.$link),
			new CLink(_('Events'), 'events.php?'.$link),
			new CLink(_('Screens'), 'host_screen.php?hostid='.$hostid),
			$applications_link,
			$items_link,
			$triggers_link,
			$graphs_link,
		));
	}

	$sysmap_menu = get_icon('menu', array('menu' => 'sysmaps'));

	$wdgt_hosts = new CUIWidget('search_hosts', $table, CProfile::get('web.search.hats.search_hosts.state', true));
	$wdgt_hosts->setHeader(_('Hosts'), SPACE);
	$wdgt_hosts->setFooter(_s('Displaying %1$s of %2$s found', $viewCount, $overalCount));

	$searchWidget->addItem(new CDiv($wdgt_hosts));
//----------------


// Find Host groups
	$params = array(
		'nodeids'=> get_current_nodeid(true),
		'output' => API_OUTPUT_EXTEND,
		'search' => array('name' => $search),
		'limit' => $rows_per_page
	);

	$db_hostGroups = API::HostGroup()->get($params);
	order_result($db_hostGroups, 'name');

	$hostGroups = selectByPattern($db_hostGroups, 'name', $search, $rows_per_page);
	$groupids = zbx_objectValues($hostGroups, 'groupid');

	$params = array(
		'nodeids'=> get_current_nodeid(true),
		'groupids' => $groupids,
		'editable' => 1
	);

	$rw_hostGroups = API::HostGroup()->get($params);
	$rw_hostGroups = zbx_toHash($rw_hostGroups, 'groupid');

	$params = array(
		'nodeids'=> get_current_nodeid(true),
		'search' => array('name' => $search),
		'countOutput' => 1
	);
	$overalCount = API::HostGroup()->get($params);
	$viewCount = count($hostGroups);

	$header = array(
		ZBX_DISTRIBUTED ? new CCol(_('Node')) : null,
		new CCol(_('Host group')),
		new CCol(_('Latest data')),
		new CCol(_('Triggers')),
		new CCol(_('Events')),
		$admin ? new CCol(_('Edit hosts')) : null,
	);

	$table  = new CTableInfo();
	$table->setHeader($header);

	foreach($hostGroups as $hnum => $group){
		$hostgroupid = $group['groupid'];

		$caption = make_decoration($group['name'], $search);
		$link = 'groupid='.$hostgroupid.'&hostid=0&switch_node='.id2nodeid($hostgroupid);

		if($admin){
			if(isset($rw_hostGroups[$hostgroupid])){
				$admin_link = new CLink(_('Edit hosts'),'hosts.php?config=1&groupid='.$hostgroupid.'&hostid=0'.'&switch_node='.id2nodeid($hostgroupid));
				$hgroup_link = new CLink($caption,'hostgroups.php?form=update&'.$link);
			}
			else{
				$admin_link = new CSpan(_('Edit hosts'),'unknown');
				$hgroup_link = new CSpan($caption);
			}
		}
		else{
			$admin_link = null;
			$hgroup_link = new CSpan($caption);
		}

		$table->addRow(array(
			get_node_name_by_elid($hostgroupid, true),
			$hgroup_link,
			new CLink(_('Latest data'), 'latest.php?'.$link),
			new CLink(_('Triggers'), 'tr_status.php?'.$link),
			new CLink(_('Events'), 'events.php?'.$link),
			$admin_link,
		));
	}

	$wdgt_hgroups = new CUIWidget('search_hostgroup', $table, CProfile::get('web.search.hats.search_hostgroup.state', true));
	$wdgt_hgroups->setHeader(_('Host groups'), SPACE);
	$wdgt_hgroups->setFooter(_s('Displaying %1$s of %2$s found', $viewCount, $overalCount));

	$searchWidget->addItem(new CDiv($wdgt_hgroups));
//----------------

// FIND Templates
	if($admin){
		$params = array(
			'nodeids'=> get_current_nodeid(true),
			'search' => array('name' => $search),
			'output' => array('name'),
			'selectGroups' => API_OUTPUT_REFER,
			'sortfield' => 'name',
			'selectItems' => API_OUTPUT_COUNT,
			'selectTriggers' => API_OUTPUT_COUNT,
			'selectGraphs' => API_OUTPUT_COUNT,
			'selectApplications' => API_OUTPUT_COUNT,
			'selectScreens' => API_OUTPUT_COUNT,
			'limit' => $rows_per_page
		);
		$db_templates = API::Template()->get($params);
		order_result($db_templates, 'name');

		$templates = selectByPattern($db_templates, 'name', $search, $rows_per_page);
		$templateids = zbx_objectValues($templates, 'templateid');

		$params = array(
			'nodeids'=> get_current_nodeid(true),
			'templateids' => $templateids,
			'editable' => 1
		);
		$rw_templates = API::Template()->get($params);
		$rw_templates = zbx_toHash($rw_templates,'templateid');

		$params = array(
			'nodeids'=> get_current_nodeid(true),
			'search' => array('name' => $search),
			'countOutput' => 1,
			'editable' => 1
		);

		$overalCount = API::Template()->get($params);
		$viewCount = count($templates);

		$header = array(
			ZBX_DISTRIBUTED ? new CCol(_('Node')) : null,
			new CCol(_('Templates')),
			new CCol(_('Applications')),
			new CCol(_('Items')),
			new CCol(_('Triggers')),
			new CCol(_('Graphs')),
			new CCol(_('Screens')),
		);

		$table  = new CTableInfo();
		$table->setHeader($header);

		foreach($templates as $tnum => $template){
			$templateid = $template['hostid'];

			$group = reset($template['groups']);
			$link = 'groupid='.$group['groupid'].'&hostid='.$templateid.'&switch_node='.id2nodeid($templateid);

			$caption = make_decoration($template['name'], $search);

			if (isset($rw_templates[$templateid])) {
				$template_link = new CLink($caption, 'templates.php?form=update&'.'&templateid='.$templateid.'&switch_node='.id2nodeid($templateid));
				$applications_link = array(new CLink(_('Applications'), 'applications.php?'.$link), ' ('.$template['applications'].')');
				$items_link = array(new CLink(_('Items'), 'items.php?filter_set=1&'.$link), ' ('.$template['items'].')');
				$triggers_link = array(new CLink(_('Triggers'), 'triggers.php?'.$link), ' ('.$template['triggers'].')');
				$graphs_link = array(new CLink(_('Graphs'), 'graphs.php?'.$link), ' ('.$template['graphs'].')');
				$screensLink = array(new CLink(_('Screens'), 'screenconf.php?templateid='.$templateid), ' ('.$template['screens'].')');
			}
			else {
				$template_link = new CSpan($caption);
				$applications_link = array(new CSpan(_('Applications'), 'unknown'), ' ('.$template['applications'].')');
				$items_link = array(new CSpan(_('Items'), 'unknown'), ' ('.$template['items'].')');
				$triggers_link = array(new CSpan(_('Triggers'), 'unknown'), ' ('.$template['triggers'].')');
				$graphs_link = array(new CSpan(_('Graphs'), 'unknown'), ' ('.$template['graphs'].')');
				$screensLink = array(new CSpan(_('Screens'), 'unknown'), ' ('.$template['screens'].')');
			}

			$table->addRow(array(
				get_node_name_by_elid($templateid, true),
				$template_link,
				$applications_link,
				$items_link,
				$triggers_link,
				$graphs_link,
				$screensLink
			));
		}

		$wdgt_templates = new CUIWidget('search_templates', $table, CProfile::get('web.search.hats.search_templates.state', true));
		$wdgt_templates->setHeader(_('Templates'), SPACE);
		$wdgt_templates->setFooter(_s('Displaying %1$s of %2$s found', $viewCount, $overalCount));
		$searchWidget->addItem(new CDiv($wdgt_templates));
	}
//----------------

	$searchWidget->show();

?>
<?php

require_once dirname(__FILE__).'/include/page_footer.php';

?>
