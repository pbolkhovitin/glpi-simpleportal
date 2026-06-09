<?php

namespace GlpiPlugin\Simpleportal\Controller;

use Config;
use Glpi\Controller\AbstractController;
use Glpi\Http\Firewall;
use Glpi\Security\Attribute\SecurityStrategy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PortalController extends AbstractController
{
    private const DEFAULT_API_USER = 'glpi';
    private const DEFAULT_API_PASS = 'glpi';

    #[Route("/", name: "simpleportal_index", methods: ['GET', 'POST'])]
    #[SecurityStrategy(Firewall::STRATEGY_NO_CHECK)]
    public function __invoke(Request $request): Response
    {
        $categories = $this->getCategories();
        $errors = [];
        $success = false;

        if ($request->isMethod('POST')) {
            $result = $this->submitTicket($request);
            $success = $result['success'];
            $errors = $result['errors'];
        }

        return $this->render('@simpleportal/portal.html.twig', [
            'categories' => $categories,
            'errors'     => $errors,
            'success'    => $success,
            'form_data'  => $request->request->all(),
        ]);
    }

    private function getCategories(): array
    {
        $db = new \DBmysql();
        $iterator = $db->request('glpi_itilcategories', [
            'WHERE'  => [
                'is_active'  => 1,
                'is_deleted' => 0,
            ],
            'ORDER'  => 'name',
        ]);

        $categories = [];
        foreach ($iterator as $data) {
            $categories[$data['id']] = $data['name'];
        }
        return $categories;
    }

    private function submitTicket(Request $request): array
    {
        $name        = trim($request->request->getString('name', ''));
        $email       = trim($request->request->getString('email', ''));
        $content     = trim($request->request->getString('content', ''));
        $category_id = $request->request->getInt('category_id', 0);

        $errors = [];
        if ($name === '') {
            $errors[] = __('Name is required.', 'simpleportal');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('A valid email is required.', 'simpleportal');
        }
        if ($content === '') {
            $errors[] = __('Description is required.', 'simpleportal');
        }

        if (count($errors) > 0) {
            return ['success' => false, 'errors' => $errors];
        }

        $config = Config::getConfigurationValues('simpleportal', [
            'api_url',
            'api_app_token',
            'api_user_token',
        ]);

        $api_url    = !empty($config['api_url']) ? $config['api_url'] : $this->getDefaultApiUrl();
        $app_token  = $config['api_app_token'] ?? '';
        $user_token = $config['api_user_token'] ?? '';

        $session_token = $this->initApiSession($api_url, $app_token, $user_token);
        if ($session_token === null) {
            return [
                'success' => false,
                'errors'  => [__('Failed to connect to GLPI API.', 'simpleportal')],
            ];
        }

        $ticket_data = [
            'input' => [
                'name'               => $name,
                'content'            => $content,
                '_users_id_requester' => 0,
                '_users_id_assign'   => 0,
                '_users_id_observer' => 0,
                'itilcategories_id'  => $category_id,
                'type'               => \Ticket::INCIDENT_TYPE,
                '_additional_emails' => [
                    ['email' => $email],
                ],
            ],
        ];

        $ch = curl_init($api_url . '/Ticket');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($ticket_data),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Session-Token: {$session_token}",
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->killApiSession($api_url, $session_token);

        if ($http_code === 201) {
            $this->sendConfirmationEmail($email, $name);
            return ['success' => true, 'errors' => []];
        }

        $body = $this->safeJsonDecode($response);
        $error_msg = $body[0]['message'] ?? sprintf('HTTP %d', $http_code);
        return [
            'success' => false,
            'errors'  => [sprintf(__('Ticket creation failed: %s', 'simpleportal'), $error_msg)],
        ];
    }

    private function initApiSession(string $api_url, string $app_token, string $user_token): ?string
    {
        $headers = [
            "App-Token: {$app_token}",
        ];

        if (!empty($user_token)) {
            $headers[] = "Authorization: user_token {$user_token}";
        } else {
            $headers[] = "Authorization: Basic " . base64_encode(self::DEFAULT_API_USER . ':' . self::DEFAULT_API_PASS);
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

        if ($http_code !== 200) {
            return null;
        }

        $body = $this->safeJsonDecode($response);
        if (isset($body['session_token'])) {
            return $body['session_token'];
        }

        return null;
    }

    private function killApiSession(string $api_url, string $session_token): void
    {
        $ch = curl_init($api_url . '/killSession');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                "Session-Token: {$session_token}",
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    private function getDefaultApiUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "{$scheme}://{$host}/apirest.php";
    }

    private function safeJsonDecode(string $data): ?array
    {
        $decoded = json_decode($data, true);
        if ($decoded !== null) {
            return $decoded;
        }

        $last_brace = strrpos($data, '}');
        if ($last_brace !== false) {
            $decoded = json_decode(substr($data, 0, $last_brace + 1), true);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        return null;
    }

    private function sendConfirmationEmail(string $email, string $name): void
    {
        $subject = sprintf(__('Your request has been received', 'simpleportal'), $name);
        $message = sprintf(
            __("Hello %s,\n\nYour support request has been submitted successfully.\nWe will get back to you as soon as possible.\n\nBest regards,\nSupport Team", 'simpleportal'),
            $name
        );
        mail($email, $subject, $message, "Content-Type: text/plain; charset=UTF-8\r\n");
    }
}
