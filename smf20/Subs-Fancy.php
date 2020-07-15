<?php

/**
 * Subs-Fancy.php
 *
 * @package Fancy Features
 * @link https://dragomano.ru/mods/fancy-features
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2010-2020 Bugo
 * @license https://opensource.org/licenses/MIT MIT
 *
 * @version 1.7.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

// Подключаем используемые хуки
function fancy_hooks()
{
	add_integration_function('integrate_actions', 'fancy_actions', false);
	add_integration_function('integrate_load_theme', 'fancy_load_theme', false);
	add_integration_function('integrate_admin_areas', 'fancy_admin_areas', false);
	add_integration_function('integrate_modify_modifications', 'fancy_modifications', false);
	add_integration_function('integrate_menu_buttons', 'fancy_menu_buttons', false);
	add_integration_function('integrate_buffer', 'fancy_buffer', false);
}

function fancy_actions(&$actions)
{
	global $modSettings;

	if (!empty($modSettings['fancy_button_help']))
		unset($actions['help']);

	if (!empty($modSettings['fancy_button_register']))
		unset($actions['register'], $actions['register2']);
}

function fancy_load_theme()
{
	global $modSettings, $txt;

	loadLanguage('Fancy');

	// IP Logged Hide
	if (!empty($modSettings['fancy_IP_hide']) && !empty($_REQUEST['topic']))
		$txt['logged'] = '';

	// Response Prefix Hide
	if (!empty($modSettings['fancy_response_prefix']))
		$txt['response_prefix'] = '';
}

function fancy_menu_buttons(&$buttons)
{
	global $modSettings, $context, $txt, $scripturl, $user_profile;

	// Прячем иконку IP
	if (!empty($modSettings['fancy_IP_hide']))
		$context['html_headers'] .= "\n\t" . '<style type="text/css">.moderatorbar img[src*="ip.gif"] {display:none}</style>';

	// Управление отображением кнопки "Помощь"
	if (!empty($modSettings['fancy_button_help']))
		unset($buttons['help']);

	// Управление отображением кнопки "Регистрация"
	if (!empty($modSettings['fancy_button_register'])) {
		unset($buttons['register']);
		$txt['welcome_guest'] = $txt['hello_guest'] . ' ' . $context['user']['name'];
	}

	// Добавление пункта "Настройки модов" в раскрывающийся список в главном меню
	if ($context['allow_admin']) {
		$counter = 0;
		foreach ($buttons['admin']['sub_buttons'] as $area => $dummy) {
			$counter++;
			if ($area == 'packages')
				break;
		}

		$buttons['admin']['sub_buttons'] = array_merge(
			array_slice($buttons['admin']['sub_buttons'], 0, $counter, true),
			array(
				'modsettings' => array(
					'title' => $txt['fancy_modifications_desc'],
					'href'  => $scripturl . '?action=admin;area=modsettings',
					'show'  => allowedTo('admin_forum')
				)
			),
			array_slice($buttons['admin']['sub_buttons'], $counter, null, true)
		);
	}

	if (!empty($_REQUEST['topic']) && !empty($user_profile)) {
		foreach (array_keys($user_profile) as $user_id) {
			// Website URL Hide (for guests)
			if (!empty($modSettings['fancy_website']) && $context['user']['is_guest'])
				$user_profile[$user_id]['website_url'] = '';

			// Groups have color that defined in settings (for every group)
			if (!empty($modSettings['fancy_color_groups']) && !empty($user_profile[$user_id]['member_group_color']))
				$user_profile[$user_id]['member_group'] = '<span style="color: ' . $user_profile[$user_id]['member_group_color'] . '">' . $user_profile[$user_id]['member_group'] . '</span>';

			if (!empty($modSettings['fancy_hide_signatures']) && $context['user']['is_guest'])
				$user_profile[$user_id]['signature'] = '';

			// Прячем иконку «Отправить e-mail» от всех, кроме админа
			if (!empty($modSettings['fancy_hide_email_icon']) && !$context['user']['is_admin'])
				$user_profile[$user_id]['hide_email'] = true;
		}
	}
}

function fancy_buffer($buffer)
{
	global $modSettings, $txt, $user_profile, $settings;

	if (isset($_REQUEST['xml']))
		return $buffer;

	$replacements = array();

	// Убираем строчку "Пользователи за последние ... минут" на главной странице
	if (!empty($modSettings['fancy_users_active'])) {
		$last_users = sprintf($txt['users_active'], $modSettings['lastActive']) . ':<br />';
		$replacements[$last_users] = '';
	}

	// ICQ Status Check
	if (!empty($_REQUEST['topic']) && !empty($user_profile)) {
		foreach (array_keys($user_profile) as $user_id) {
			$icq_old = '<a class="icq new_win" href="http://www.icq.com/whitepages/about_me.php?uin=' . $user_profile[$user_id]['icq'] . '" target="_blank" title="' . $txt['icq_title'] . ' - ' . $user_profile[$user_id]['icq'] . '"><img src="http://status.icq.com/online.gif?img=5&amp;icq=' . $user_profile[$user_id]['icq'] . '" alt="' . $txt['icq_title'] . ' - ' . $user_profile[$user_id]['icq'] . '" width="18" height="18" /></a>';
			$icq_new = '<img src="' . $settings['default_images_url'] . '/icq.gif" alt="" title="' . $user_profile[$user_id]['icq'] . '" />';
			$replacements[$icq_old] = $icq_new;
		}
	}

	return str_replace(array_keys($replacements), array_values($replacements), $buffer);
}

function fancy_admin_areas(&$admin_areas)
{
	global $txt;

	$admin_areas['config']['areas']['modsettings']['subsections']['fancy_features'] = array($txt['fancy']);
}

function fancy_modifications(&$subActions)
{
	$subActions['fancy_features'] = 'fancy_settings';
}

function fancy_settings()
{
	global $context, $txt, $scripturl, $modSettings;

	$context['page_title']     = $txt['fancy'];
	$context['settings_title'] = $txt['fancy_ext'];
	$context['post_url']       = $scripturl . '?action=admin;area=modsettings;save;sa=fancy_features';
	$context[$context['admin_menu_name']]['tab_data']['tabs']['fancy_features'] = array('description' => $txt['fancy_desc']);

	$config_vars = array(
		array('check', 'fancy_color_groups'),
		array('check', 'fancy_users_active'),
		array('check', 'fancy_ICQ_status'),
		array('check', 'fancy_website'),
		array('check', 'fancy_hide_email_icon'),
		array('check', 'fancy_response_prefix'),
		array('check', 'fancy_IP_hide'),
		array('check', 'fancy_button_help'),
		array('check', 'fancy_button_register'),
		array('check', 'fancy_hide_signatures')
	);

	// Saving?
	if (isset($_GET['save'])) {
		checkSession();
		saveDBSettings($config_vars);

		if (!empty($modSettings['fancy_memory_usage']) && empty($modSettings['timeLoadPageEnable']))
			updateSettings(array('timeLoadPageEnable' => 1));

		redirectexit('action=admin;area=modsettings;sa=fancy_features');
	}

   	prepareDBSettingContext($config_vars);
}
