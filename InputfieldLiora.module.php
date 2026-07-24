<?php namespace ProcessWire;

/**
 * Reusable ProcessWire Inputfield preview for the Liora CTA.
 *
 * The input value becomes the original search/query context. The Inputfield is
 * display-only; questions are persisted by LioraStore rather than page fields.
 */
class InputfieldLiora extends Inputfield {

    public static function getModuleInfo(): array {
        return [
            'title' => 'Inputfield Liora',
            'version' => 140,
            'summary' => 'Reusable Liora AI CTA and chat Inputfield.',
            'author' => 'Maxim Semenov',
            'icon' => 'commenting',
            'requires' => ['Liora'],
        ];
    }

    public function ___render(): string {
        $liora = $this->wire('modules')->get('Liora');
        if(!$liora) return '<p class="uk-text-danger">' . $this->_('Liora is not installed.') . '</p>';
        return $liora->renderWidget([
            'originalQuery' => trim((string)$this->value),
            'context' => 'inputfield-preview',
            'compact' => true,
        ]);
    }

    public function ___processInput(WireInputData $input): static {
        return $this;
    }
}
