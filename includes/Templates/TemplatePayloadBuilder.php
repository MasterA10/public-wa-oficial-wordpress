<?php
namespace WAS\Templates;

use RuntimeException;
use WAS\Core\SystemLogger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Construtor de Payload oficial para a Meta Graph API.
 * Transforma o "friendly_payload" do frontend no formato técnico exigido.
 */
final class TemplatePayloadBuilder
{
    public function build(array $friendly): array
    {
        SystemLogger::logInfo('PayloadBuilder: Iniciando build do template.', [
            'name'     => $friendly['name'] ?? null,
            'category' => $friendly['category'] ?? null,
            'language' => $friendly['language'] ?? null,
            'has_header'  => !empty($friendly['header']),
            'has_footer'  => !empty($friendly['footer']['text']),
            'has_buttons' => !empty($friendly['buttons']),
        ]);

        // Tratar categoria AUTHENTICATION de forma isolada
        if (($friendly['category'] ?? '') === 'AUTHENTICATION') {
            $authBuilder = new AuthenticationTemplatePayloadBuilder();
            return [
                'meta_payload' => $authBuilder->build($friendly),
                'variable_map' => ['1' => 'código'] // Autenticação COPY_CODE sempre usa 1 variável
            ];
        }

        $components = [];
        $variableMap = [];

        // 1. HEADER
        if (!empty($friendly['header']) && $this->shouldAddHeader($friendly['header'])) {
            $headerComponent = $this->buildHeader($friendly['header']);
            $components[] = $headerComponent;
            SystemLogger::logInfo('PayloadBuilder: Header adicionado.', [
                'format' => $headerComponent['format'] ?? 'N/A',
            ]);
        }

        // 2. BODY (Obrigatório)
        $bodyResult = $this->buildBody($friendly['body'] ?? []);
        $components[] = $bodyResult['component'];
        $variableMap = $bodyResult['variable_map'];

        SystemLogger::logInfo('PayloadBuilder: Body construído.', [
            'variables_count' => count($variableMap),
            'has_examples'    => !empty($bodyResult['component']['example']),
            'text_preview'    => mb_substr($bodyResult['component']['text'] ?? '', 0, 100),
        ]);

        // 3. FOOTER
        if (!empty(trim($friendly['footer']['text'] ?? ''))) {
            $components[] = [
                'type' => 'FOOTER',
                'text' => trim($friendly['footer']['text']),
            ];
        }

        // 4. BUTTONS
        if (!empty($friendly['buttons']) && is_array($friendly['buttons'])) {
            $buttons = $this->buildButtons($friendly['buttons'], $variableMap);

            if (!empty($buttons)) {
                $components[] = [
                    'type' => 'BUTTONS',
                    'buttons' => $buttons,
                ];
                SystemLogger::logInfo('PayloadBuilder: Botões adicionados.', [
                    'count' => count($buttons),
                    'types' => array_column($buttons, 'type'),
                ]);
            }
        }

        $metaPayload = [
            'name'             => $this->normalizeName($friendly['name'] ?? ''),
            'category'         => strtoupper($friendly['category'] ?? 'UTILITY'),
            'language'         => $friendly['language'] ?? 'pt_BR',
            'components'       => $components,
        ];

        SystemLogger::logInfo('PayloadBuilder: Payload final montado.', [
            'payload_name'      => $metaPayload['name'],
            'components_count'  => count($components),
            'variable_map'      => $variableMap,
        ]);

        return [
            'meta_payload' => $metaPayload,
            'variable_map' => $variableMap,
        ];
    }

    private function shouldAddHeader(array $header): bool
    {
        $type = strtoupper($header['type'] ?? 'NONE');

        if ($type === 'NONE') {
            return false;
        }

        if ($type === 'TEXT') {
            return trim((string) ($header['text'] ?? '')) !== '';
        }

        // Futuro suporte para mídia
        if (in_array($type, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            return !empty($header['media_handle']);
        }

        return false;
    }

    private function buildHeader(array $header): array
    {
        $type = strtoupper($header['type'] ?? 'NONE');

        if ($type === 'TEXT') {
            return [
                'type' => 'HEADER',
                'format' => 'TEXT',
                'text' => trim($header['text']),
            ];
        }

        if (in_array($type, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            return [
                'type' => 'HEADER',
                'format' => $type,
                'example' => [
                    'header_handle' => [
                        $header['media_handle'],
                    ],
                ],
            ];
        }

        throw new RuntimeException('Header inválido.');
    }

    private function buildBody(array $body): array
    {
        $text = trim((string) ($body['text'] ?? ''));

        if ($text === '') {
            throw new RuntimeException('O corpo do template é obrigatório.');
        }

        $this->assertNoLeadingOrTrailingVariables($text);

        $examples = [];
        foreach (($body['variables'] ?? []) as $variable) {
            $key = $variable['key'] ?? '';
            $value = $variable['example'] ?? '';
            if ($key !== '') {
                $examples[$key] = $value;
            }
        }

        $parsed = $this->parseVariables($text, $examples);

        $component = [
            'type' => 'BODY',
            'text' => $parsed['text'],
        ];

        // Meta exige: example -> body_text -> [ [val1, val2] ]
        if (!empty($parsed['examples'])) {
            $component['example'] = [
                'body_text' => [
                    $parsed['examples'],
                ],
            ];
        }

        return [
            'component' => $component,
            'variable_map' => $parsed['variable_map'],
        ];
    }

    /**
     * Valida que o texto não começa nem termina com variáveis.
     */
    private function assertNoLeadingOrTrailingVariables(string $text): void
    {
        $text = trim($text);

        // Verifica início: {{var}} ...
        if (preg_match('/^{{\s*[a-zA-Z0-9_]+\s*}}/', $text)) {
            throw new RuntimeException(
                'A Meta não permite que o template comece com uma variável. Adicione algum texto antes dela (ex: "Olá, {{nome}}").'
            );
        }

        // Verifica fim: ... {{var}}
        if (preg_match('/{{\s*[a-zA-Z0-9_]+\s*}}$/', $text)) {
            throw new RuntimeException(
                'A Meta não permite que o template termine com uma variável. Adicione algum texto depois dela (ex: "{{codigo}}.").'
            );
        }
    }

    /**
     * Converte {{nome}} para {{1}} e extrai mapa de variáveis.
     */
    private function parseVariables(string $text, array $examples): array
    {
        preg_match_all('/{{\s*([a-zA-Z0-9_]+)\s*}}/', $text, $matches);
        $names = array_values(array_unique($matches[1] ?? []));

        $variableMap = [];
        $exampleValues = [];
        $metaText = $text;

        foreach ($names as $index => $name) {
            $position = $index + 1;

            if (!isset($examples[$name]) || trim((string) $examples[$name]) === '') {
                throw new RuntimeException("A variável {{$name}} precisa de um valor de exemplo.");
            }

            $variableMap[(string) $position] = $name;
            $exampleValues[] = (string) $examples[$name];

            $metaText = preg_replace(
                '/{{\s*' . preg_quote($name, '/') . '\s*}}/',
                '{{' . $position . '}}',
                $metaText
            );
        }

        return [
            'text' => $metaText,
            'variable_map' => $variableMap,
            'examples' => $exampleValues,
        ];
    }

    private function buildButtons(array $buttons, array $variableMap): array
    {
        $result = [];

        foreach ($buttons as $button) {
            $type = strtoupper($button['type'] ?? '');

            if ($type === 'QUICK_REPLY') {
                $result[] = [
                    'type' => 'QUICK_REPLY',
                    'text' => trim($button['text']),
                ];
            }

            if ($type === 'PHONE_NUMBER') {
                $result[] = [
                    'type' => 'PHONE_NUMBER',
                    'text' => trim($button['text']),
                    'phone_number' => trim($button['phone_number']),
                ];
            }

            if ($type === 'URL') {
                // A URL pode ter variável dinâmica. Ex: domain.com/{{pedido}}
                // No payload de criação da Meta, a URL deve vir com {{1}}
                $url = trim($button['url']);
                
                // Mapeia variáveis amigáveis para posicionais na URL
                foreach ($variableMap as $pos => $friendlyName) {
                    $url = preg_replace('/{{\s*'.preg_quote($friendlyName, '/').'\s*}}/', '{{'.$pos.'}}', $url);
                }

                $payload = [
                    'type' => 'URL',
                    'text' => trim($button['text']),
                    'url' => $url,
                ];

                // Se for dinâmica, exige example -> array de strings
                if (str_contains($url, '{{')) {
                    if (empty($button['example'])) {
                        throw new RuntimeException("O botão de URL dinâmica '{$button['text']}' precisa de um valor de exemplo.");
                    }
                    $payload['example'] = [(string) $button['example']];
                }

                $result[] = $payload;
            }

            if ($type === 'COPY_CODE') {
                $result[] = [
                    'type' => 'COPY_CODE',
                    'example' => (string) ($button['example'] ?? 'CODE'),
                ];
            }
        }

        return $result;
    }

    private function normalizeName(string $name): string
    {
        $name = strtolower($name);
        if (function_exists('remove_accents')) {
            $name = remove_accents($name);
        }
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name);
        $name = trim($name, '_');

        if ($name === '') {
            throw new RuntimeException('Nome do template inválido.');
        }

        return $name;
    }
}
