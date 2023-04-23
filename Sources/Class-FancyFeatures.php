<?php

/**
 * Class-FancyFeatures.php
 *
 * @package Fancy Features
 * @link https://dragomano.ru/mods/fancy-features
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2010-2023 Bugo
 * @license https://opensource.org/licenses/MIT MIT
 *
 * @version 1.11
 */

if (!defined('SMF'))
	die('No direct access...');

final class FancyFeatures
{
	public function hooks()
	{
		add_integration_function('integrate_actions', __CLASS__ . '::actions#', false, __FILE__);
		add_integration_function('integrate_load_theme', __CLASS__ . '::loadTheme#', false, __FILE__);
		add_integration_function('integrate_admin_areas', __CLASS__ . '::adminAreas#', false, __FILE__);
		add_integration_function('integrate_admin_search', __CLASS__ . '::adminSearch#', false, __FILE__);
		add_integration_function('integrate_modify_modifications', __CLASS__ . '::modifications#', false, __FILE__);
		add_integration_function('integrate_menu_buttons', __CLASS__ . '::menuButtons#', false, __FILE__);
		add_integration_function('integrate_prepare_display_context', __CLASS__ . '::prepareDisplayContext#', false, __FILE__);
		add_integration_function('integrate_buffer', __CLASS__ . '::buffer#', false, __FILE__);
		add_integration_function('integrate_metazones', __CLASS__ . '::metazones#', false, __FILE__);
	}

	public function actions(array &$actions)
	{
		global $modSettings;

		if (!empty($modSettings['fancy_hide_help_link']))
			unset($actions['help']);

		if (!empty($modSettings['fancy_hide_register_button']))
			unset($actions['signup'], $actions['signup2']);
	}

	public function loadTheme()
	{
		global $modSettings, $txt;

		// Response Prefix Hide
		if (!empty($modSettings['fancy_hide_response_prefix']))
			$txt['response_prefix'] = '';
	}

	public function menuButtons(array &$buttons)
	{
		global $modSettings, $txt, $user_profile, $context;

		// Управление отображением кнопки «Регистрация»
		if (!empty($modSettings['fancy_hide_register_button'])) {
			unset($buttons['signup']);

			$txt['welcome_guest_register'] = $txt['welcome_guest'];
		}

		if (!empty($_REQUEST['topic']) && !empty($user_profile)) {
			foreach (array_keys($user_profile) as $user_id) {
				// Прячем адрес сайта пользователя (если страницу смотрит гость)
				if (!empty($modSettings['fancy_hide_website']) && $context['user']['is_guest'])
					$user_profile[$user_id]['website_url'] = '';

				// У названий групп в сообщениях тот же цвет, что и в настройках этих групп
				if (!empty($modSettings['fancy_color_groups']) && !empty($user_profile[$user_id]['member_group_color']))
					$user_profile[$user_id]['member_group'] = '<span style="color: ' . $user_profile[$user_id]['member_group_color'] . '">' . $user_profile[$user_id]['member_group'] . '</span>';

				// Прячем иконку «E-mail» от всех, кроме админа
				if (!empty($modSettings['fancy_hide_email_icon']) && !$context['user']['is_admin'])
					$user_profile[$user_id]['email_address'] = '';
			}
		}
	}

	public function prepareDisplayContext(array &$output, array &$message)
	{
		global $modSettings;

		if (!empty($modSettings['fancy_highlight_admin_posts']) && !empty($output['member']['group_id']) && $output['member']['group_id'] == 1) {
			$output['css_class'] .= ' sticky';
		}

		if (!empty($modSettings['fancy_lock_post']) && !empty($message['modified_name']) && $message['modified_name'] != $message['poster_name']) {
			$output['can_modify'] = false;
			$output['quickbuttons']['quick_edit']['show'] = false;
			$output['quickbuttons']['more']['modify']['show'] = false;
		}

		if (!empty($modSettings['fancy_hide_last_change']) && !empty($output['modified']['time']) && !empty($message['modified_name']) && $message['modified_name'] != $message['poster_name']) {
			$output['modified']['name'] = '';
		}
	}

	public function buffer(string $buffer): string
	{
		global $modSettings, $scripturl, $txt;

		if (isset($_REQUEST['xml']))
			return $buffer;

		$replacements = array();

		// Прячем IP
		if (!empty($modSettings['fancy_hide_ip']) && !empty($_REQUEST['topic'])) {
			$poster_ip_text = '<li class="poster_ip">';
			$replacements[$poster_ip_text] = '<li class="poster_ip" style="display: none">';
		}

		// Управление отображением ссылки «Помощь»
		if (!empty($modSettings['fancy_hide_help_link'])) {
			$help_link = '<a href="' . $scripturl . '?action=help">' . $txt['help'] . '</a> ' . (!empty($modSettings['requireAgreement']) ? '| ' : ' |');
			$replacements[$help_link] = '';
		}

		// Убираем строчку «Пользователи за последние ... минут» на главной странице
		if (!empty($modSettings['fancy_hide_users_active_msg'])) {
			$last_users = sprintf($txt['users_active'], $modSettings['lastActive']) . ': ';
			$replacements[$last_users] = '';
		}

		return str_replace(array_keys($replacements), array_values($replacements), $buffer);
	}

	public function metazones(array &$tzid_metazones)
	{
		global $modSettings, $tztxt;

		if (empty($modSettings['fancy_improve_timezone_list']))
			return;

		$tztxt['generic_timezone'] = '%1$s';
		$tzid_metazones = array();
	}

	public function adminAreas(array &$admin_areas)
	{
		global $txt;

		loadLanguage('FancyFeatures');

		$admin_areas['config']['areas']['modsettings']['subsections']['fancy_features'] = array($txt['fancy']);
	}

	public function adminSearch(array &$language_files, array &$include_files, array &$settings_search)
	{
		$language_files[] = 'FancyFeatures';

		$settings_search[] = array(array($this, 'settings'), 'area=modsettings;sa=fancy_features');
	}

	public function modifications(array &$subActions)
	{
		$subActions['fancy_features'] = array($this, 'settings');
	}

	/**
	 * @return array|void
	 */
	public function settings(bool $return_config = false)
	{
		global $context, $txt, $scripturl, $modSettings;

		loadLanguage('FancyFeatures');

		$context['page_title']     = $txt['fancy'];
		$context['settings_title'] = $txt['fancy_ext'];
		$context['post_url']       = $scripturl . '?action=admin;area=modsettings;save;sa=fancy_features';

		$config_vars = array(
			array('check', 'fancy_color_groups'),
			array('check', 'fancy_highlight_admin_posts'),
			array('check', 'fancy_lock_post'),
			array('check', 'fancy_hide_last_change'),
			array('check', 'fancy_hide_users_active_msg'),
			array('check', 'fancy_hide_website'),
			array('check', 'fancy_hide_email_icon'),
			array('check', 'fancy_hide_response_prefix'),
			array('check', 'fancy_hide_ip'),
			array('check', 'fancy_hide_help_link'),
			array('check', 'fancy_hide_register_button'),
			array('check', 'fancy_improve_timezone_list', 'help' => $txt['fancy_improve_timezone_list_help'], 'disabled' => empty($modSettings['timezone_priority_countries'])),
			array('check', 'disableRegisterCheck', 'help' => $txt['fancy_disable_register_check_help']),
			array('check', 'disable_smf_js')
		);

		if ($return_config)
			return $config_vars;

		$context[$context['admin_menu_name']]['tab_data']['description'] = $txt['fancy_desc'];

		// Saving?
		if (isset($_GET['save'])) {
			checkSession();

			$save_vars = $config_vars;
			saveDBSettings($save_vars);

			redirectexit('action=admin;area=modsettings;sa=fancy_features');
		}

		prepareDBSettingContext($config_vars);
	}
}
