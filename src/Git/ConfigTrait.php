<?php namespace ProcessWire;

trait LioraGitConfigTrait {

    protected function gitSetting(string $name, $default = '') {
        $value = $this->get($name);
        return $value === null || $value === '' ? $default : $value;
    }

    protected function repository(): string {
        $value = trim((string)$this->gitSetting('repository'));
        if(!preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $value)) throw new WireException('Configure a valid owner/repository');
        return $value;
    }

    protected function branch(): string {
        $value = trim((string)$this->gitSetting('branch', 'main'));
        if(!preg_match('#^[A-Za-z0-9._/-]{1,190}$#', $value) || str_contains($value, '..')) throw new WireException('Invalid branch');
        return $value;
    }

    protected function collection(): string {
        $value = trim((string)$this->gitSetting('collection', 'liora-git'));
        if(!preg_match('/^(?!__atlas_stage_)[a-z0-9._-]{1,128}$/i', $value)) throw new WireException('Invalid Atlas collection');
        return $value;
    }

    protected function writeDirectory(): string {
        $value = trim((string)$this->gitSetting('writeDirectory', 'memory/inbox'), '/');
        if(!$this->validPath($value) || $value === '') throw new WireException('Invalid write directory');
        return $value;
    }

    protected function validPath(string $path): bool {
        return $path !== '' && mb_strlen($path) <= 500 && !str_starts_with($path, '/') && !str_contains($path, '..') && !str_contains($path, "\0") && preg_match('#^[A-Za-z0-9._/ -]+$#u', $path) === 1;
    }

    public static function getModuleConfigInputfields(array $data): InputfieldWrapper {
        $modules = wire('modules');
        $fields = new InputfieldWrapper();
        $addText = static function(string $name, string $label, string $default = '', string $description = '') use ($modules, $fields, $data): void {
            $field = $modules->get('InputfieldText'); $field->name = $name; $field->label = $label;
            $field->value = (string)($data[$name] ?? $default); $field->description = $description; $fields->add($field);
        };
        $addText('repository', 'GitHub repository', '', 'One repository in owner/name form.');
        $addText('branch', 'Branch', 'main');
        $addText('collection', 'Private Atlas collection', 'liora-git');
        $addText('readPaths', 'Read paths', "README.md\n**/*.md", 'Comma or newline-separated glob patterns.');
        $addText('writeDirectory', 'Write directory', 'memory/inbox', 'All writes are restricted to this directory.');
        $addText('endpoint', 'Private memory endpoint', '/liora-memory/', 'URL of a thin ProcessWire template that calls LioraGit::handleMemoryEndpoint().');
        foreach(['readToken' => 'GitHub read token', 'writeToken' => 'GitHub write token'] as $name => $label) {
            $field = $modules->get('InputfieldText'); $field->name = $name; $field->label = $label;
            $field->value = (string)($data[$name] ?? ''); $field->attr('type', 'password');
            $field->description = $name === 'writeToken' ? 'Optional separate token. It never falls back to the read token.' : 'Fine-grained Contents: read token scoped to this repository.';
            $fields->add($field);
        }
        foreach([
            'maxDocuments' => ['Maximum documents per sync', 2000, 1, 10000],
            'maxFileBytes' => ['Maximum Markdown file bytes', 250000, 1000, 1000000],
            'chunkChars' => ['Atlas chunk characters', 1800, 500, 5000],
        ] as $name => [$label, $default, $min, $max]) {
            $field = $modules->get('InputfieldInteger'); $field->name = $name; $field->label = $label;
            $field->value = (int)($data[$name] ?? $default); $field->min = $min; $field->max = $max; $fields->add($field);
        }
        return $fields;
    }
}
