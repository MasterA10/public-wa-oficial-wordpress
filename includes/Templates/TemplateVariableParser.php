<?php
namespace WAS\Templates;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateVariableParser {
    /**
     * Converte {{nome}} para {{1}} e gera o mapa.
     */
    public function parse(string $text, array $examples): array {
        // Encontra todas as ocorrências de {{variavel}}
        preg_match_all('/{{\s*([a-zA-Z0-9_]+)\s*}}/', $text, $matches);
        
        $unique_vars = array_values(array_unique($matches[1] ?? []));
        $map = [];
        $meta_text = $text;
        $example_values = [];

        foreach ($unique_vars as $index => $var_name) {
            $position = $index + 1;
            $map[(string)$position] = $var_name;

            // Substitui no texto para formato Meta
            $meta_text = preg_replace(
                '/{{\s*' . preg_quote($var_name, '/') . '\s*}}/',
                '{{' . $position . '}}',
                $meta_text
            );

            // Valida exemplo
            $example_values[] = $examples[$var_name] ?? 'Exemplo';
        }

        return [
            'meta_text' => $meta_text,
            'variable_map' => $map,
            'example_values' => $example_values,
        ];
    }
}
