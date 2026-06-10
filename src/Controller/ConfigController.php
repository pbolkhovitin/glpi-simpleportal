<?php

namespace GlpiPlugin\Simpleportal\Controller;

use CommonDBTM;
use Config;
use DB;
use DBmysqlIterator;
use Dropdown;
use Entity;
use Glpi\Application\View\TemplateRenderer;
use Glpi\Controller\AbstractController;
use Glpi\Http\Firewall;
use Glpi\Security\Attribute\SecurityStrategy;
use Html;
use Plugin;
use Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Ticket;
use Toolbox;

#[Route("/config", name: "simpleportal_config")]
final class ConfigController extends AbstractController
{
    #[SecurityStrategy(Firewall::STRATEGY_ADMIN_ACCESS)]
    public function __invoke(Request $request): Response
    {
        Session::checkRight('config', UPDATE);

        if ($request->isMethod('POST')) {
            Config::setConfigurationValues('simpleportal', [
                'default_entity_id'      => $request->request->getInt('default_entity_id', 0),
                'ticket_type'            => $request->request->getInt('ticket_type', 1),
                'notification_enabled'   => $request->request->getInt('notification_enabled', 0),
                'notification_email_from' => $request->request->getString('notification_email_from', ''),
            ]);
            Html::back();
        }

        $config = Config::getConfigurationValues('simpleportal', [
            'default_entity_id',
            'ticket_type',
            'notification_enabled',
            'notification_email_from',
        ]);

        $info = $this->collectTechInfo();

        $entities_list = [0 => __('Root entity')];
        foreach ((new Entity())->find([], ['name ASC']) as $e) {
            $entities_list[$e['id']] = $e['name'];
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $portal_url = "{$scheme}://{$host}/plugins/simpleportal/";

        ob_start();
        Html::header(__('SimplePortal Configuration', 'simpleportal'), '', 'config', 'plugin');
        echo TemplateRenderer::getInstance()->render('@simpleportal/config.html.twig', [
            'config'      => $config,
            'info'        => $info,
            'entities'    => $entities_list,
            'portal_url'  => $portal_url,
        ]);
        Html::footer();
        return new Response(ob_get_clean());
    }

    private function collectTechInfo(): array
    {
        global $DB;

        $api_test = $this->testApiConnection();

        $ticket_count = 0;
        try {
            $ticket_count = $DB->countElementsInTable('glpi_tickets', [
                'WHERE' => ['name' => ['LIKE', 'Portal: %']],
            ]);
        } catch (\Throwable) {
        }

        return [
            'plugin_version'    => PLUGIN_SIMPLEPORTAL_VERSION,
            'glpi_version'      => GLPI_VERSION,
            'php_version'       => PHP_VERSION,
            'db_connected'      => $DB instanceof DB && $DB->connected,
            'db_host'           => $DB->dbhost ?? 'N/A',
            'db_name'           => $DB->dbdefault ?? 'N/A',
            'api_ok'            => $api_test['ok'],
            'api_message'       => $api_test['message'],
            'ticket_count'      => $ticket_count,
            'plugin_dir'        => Plugin::getPhpDir('simpleportal'),
            'php_memory_limit'  => ini_get('memory_limit'),
            'php_max_exec_time' => ini_get('max_execution_time'),
        ];
    }

    private function testApiConnection(): array
    {
        $config = Config::getConfigurationValues('simpleportal', [
            'api_url',
            'api_app_token',
            'api_user_token',
        ]);

        $api_url   = !empty($config['api_url']) ? $config['api_url'] : $this->getApiUrl();
        $app_token = $config['api_app_token'] ?? '';
        $user_token = $config['api_user_token'] ?? '';

        $headers = [];
        if (!empty($app_token)) {
            $headers[] = "App-Token: {$app_token}";
        }
        if (!empty($user_token)) {
            $headers[] = "Authorization: user_token {$user_token}";
        } else {
            $headers[] = "Authorization: Basic " . base64_encode('glpi:glpi');
        }

        $ch = curl_init($api_url . '/initSession');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            return ['ok' => true, 'message' => __('API connection successful', 'simpleportal')];
        }
        return ['ok' => false, 'message' => sprintf('HTTP %d', $http_code)];
    }

    private function getApiUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "{$scheme}://{$host}/apirest.php";
    }
}
