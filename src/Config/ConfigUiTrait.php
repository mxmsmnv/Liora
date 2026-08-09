<?php namespace ProcessWire;

trait LioraConfigUiTrait {

    public function getModuleConfigInputfields(InputfieldWrapper $inputfields): InputfieldWrapper {
        $modules = $this->wire('modules');

        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = $this->_('AI model');
        $fieldset->icon = 'brain';

        $field = $modules->get('InputfieldSelect');
        $field->attr('name', 'providerModel');
        $field->label = $this->_('Provider and model');
        $field->description = $this->_('Credentials remain in Squad. Liora can follow the Squad default or use a specific active provider/model.');
        $field->addOption('', $this->_('Use Squad default'));
        foreach($this->modelOptions() as $value => $label) $field->addOption($value, $label);
        $field->attr('value', (string)$this->setting('providerModel', ''));
        $fieldset->add($field);

        $field = $modules->get('InputfieldTextarea');
        $field->attr('name', 'systemPrompt');
        $field->label = $this->_('System prompt');
        $field->attr('rows', 8);
        $field->attr('value', (string)$this->setting('systemPrompt', $this->defaultSystemPrompt()));
        $fieldset->add($field);

        $field = $modules->get('InputfieldCheckbox');
        $field->attr('name', 'restrictExternalLinks');
        $field->label = $this->_('Keep visitors on this website');
        $field->description = $this->_('Prevents Liora from directing visitors to external websites. Same-site Atlas sources remain available.');
        if((bool)$this->setting('restrictExternalLinks', true)) $field->attr('checked', 'checked');
        $fieldset->add($field);

        $field = $modules->get('InputfieldTextarea');
        $field->attr('name', 'externalLinksPrompt');
        $field->label = $this->_('Stay-on-site instruction');
        $field->description = $this->_('Appended to the system prompt when the option above is enabled. External absolute URLs are also filtered server-side.');
        $field->attr('rows', 5);
        $field->attr('value', $this->configuredExternalLinksPrompt());
        $field->showIf = 'restrictExternalLinks=1';
        $fieldset->add($field);

        $field = $modules->get('InputfieldInteger');
        $field->attr('name', 'maxTokens');
        $field->label = $this->_('Maximum response tokens');
        $field->attr('value', (int)$this->setting('maxTokens', 1200));
        $field->attr('min', 64);
        $field->attr('max', 20000);
        $field->columnWidth = 25;
        $fieldset->add($field);

        $field = $modules->get('InputfieldText');
        $field->attr('name', 'temperature');
        $field->label = $this->_('Temperature');
        $field->attr('type', 'number');
        $field->attr('step', '0.1');
        $field->attr('min', '0');
        $field->attr('max', '2');
        $field->attr('value', (string)$this->setting('temperature', '0.4'));
        $field->columnWidth = 25;
        $fieldset->add($field);

        $field = $modules->get('InputfieldInteger');
        $field->attr('name', 'timeout');
        $field->label = $this->_('Timeout (seconds)');
        $field->attr('value', (int)$this->setting('timeout', 60));
        $field->attr('min', 5);
        $field->attr('max', 300);
        $field->columnWidth = 25;
        $fieldset->add($field);

        $field = $modules->get('InputfieldInteger');
        $field->attr('name', 'cacheSeconds');
        $field->label = $this->_('Cache lifetime (seconds)');
        $field->description = $this->_('Set to 0 to disable response caching.');
        $field->attr('value', (int)$this->setting('cacheSeconds', 3600));
        $field->attr('min', 0);
        $field->columnWidth = 25;
        $fieldset->add($field);

        $field = $modules->get('InputfieldCheckbox');
        $field->attr('name', 'streamingEnabled');
        $field->label = $this->_('Stream answers as they are generated');
        $field->description = $this->_('Uses Squad streaming and sends real provider deltas to the browser.');
        if((bool)$this->setting('streamingEnabled', true)) $field->attr('checked', 'checked');
        $fieldset->add($field);

        $field = $modules->get('InputfieldCheckbox');
        $field->attr('name', 'webSearchEnabled');
        $field->label = $this->_('Allow live web search');
        $field->description = $this->_('Lets Squad search the public web when an answer needs current information. Search can add provider charges and response time; indexed site content remains a separate source.');
        if((bool)$this->setting('webSearchEnabled', false)) $field->attr('checked', 'checked');
        $fieldset->add($field);

        $field = $modules->get('InputfieldSelect');
        $field->attr('name', 'webSearchMode');
        $field->label = $this->_('When to search the web');
        $field->addOption('auto', $this->_('Automatic — only when current information is needed'));
        $field->addOption('always', $this->_('Always — search for every question'));
        $field->attr('value', (string)$this->setting('webSearchMode', 'auto'));
        $field->showIf = 'webSearchEnabled=1';
        $fieldset->add($field);

        $field = $modules->get('InputfieldInteger');
        $field->attr('name', 'webSearchMaxResults');
        $field->label = $this->_('Maximum web results');
        $field->description = $this->_('Passed to Squad as a bounded search result or tool-use count.');
        $field->attr('value', (int)$this->setting('webSearchMaxResults', 5));
        $field->attr('min', 1);
        $field->attr('max', 10);
        $field->showIf = 'webSearchEnabled=1';
        $fieldset->add($field);
        $inputfields->add($fieldset);

        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = $this->_('Atlas knowledge (optional)');
        $fieldset->icon = 'database';
        $fieldset->collapsed = Inputfield::collapsedYes;

        $field = $modules->get('InputfieldCheckbox');
        $field->attr('name', 'atlasEnabled');
        $field->label = $this->_('Use Atlas retrieval for Liora answers');
        $field->description = $this->_('Retrieves relevant excerpts from an Atlas collection before asking the selected Squad model. Liora falls back to a normal answer when Atlas is unavailable or has no useful result.');
        if((bool)$this->setting('atlasEnabled', false)) $field->attr('checked', 'checked');
        $fieldset->add($field);

        $field = $modules->get('InputfieldSelect');
        $field->attr('name', 'atlasRetrievalMode');
        $field->label = $this->_('Retrieval mode');
        $field->description = $this->_('Automatic is recommended. Semantic retrieval creates a query embedding and can add several seconds and provider charges.');
        $field->addOption('auto', $this->_('Automatic — fast first, semantic only for site-specific questions'));
        $field->addOption('fast', $this->_('Fast — local lexical search only'));
        $field->addOption('hybrid', $this->_('Hybrid — lexical search, then semantic fallback'));
        $field->addOption('semantic', $this->_('Semantic — vector search for every question'));
        $field->attr('value', $this->atlasRetrievalMode());
        $field->showIf = 'atlasEnabled=1';
        $fieldset->add($field);

        $field = $modules->get('InputfieldText');
        $field->attr('name', 'atlasCollection');
        $field->label = $this->_('Atlas collection');
        $field->attr('value', (string)$this->setting('atlasCollection', 'site'));
        $field->columnWidth = 40;
        $fieldset->add($field);

        $field = $modules->get('InputfieldInteger');
        $field->attr('name', 'atlasTopK');
        $field->label = $this->_('Retrieved excerpts');
        $field->attr('value', (int)$this->setting('atlasTopK', 4));
        $field->attr('min', 1);
        $field->attr('max', 10);
        $field->columnWidth = 20;
        $fieldset->add($field);

        $field = $modules->get('InputfieldText');
        $field->attr('name', 'atlasMinScore');
        $field->label = $this->_('Minimum relevance');
        $field->description = $this->_('Cosine similarity from -1 to 1. Lower this if relevant material is being skipped.');
        $field->attr('type', 'number');
        $field->attr('step', '0.05');
        $field->attr('min', '-1');
        $field->attr('max', '1');
        $field->attr('value', (string)$this->setting('atlasMinScore', '0.2'));
        $field->columnWidth = 20;
        $fieldset->add($field);

        $field = $modules->get('InputfieldText');
        $field->attr('name', 'atlasLexicalMinScore');
        $field->label = $this->_('Lexical minimum relevance');
        $field->description = $this->_('Local term-match score from 0 to 1. This is intentionally separate from vector cosine similarity.');
        $field->attr('type', 'number');
        $field->attr('step', '0.01');
        $field->attr('min', '0');
        $field->attr('max', '1');
        $field->attr('value', (string)$this->setting('atlasLexicalMinScore', '0.24'));
        $field->columnWidth = 20;
        $fieldset->add($field);

        $field = $modules->get('InputfieldInteger');
        $field->attr('name', 'atlasMaxContextChars');
        $field->label = $this->_('Maximum context characters');
        $field->attr('value', (int)$this->setting('atlasMaxContextChars', 6000));
        $field->attr('min', 500);
        $field->attr('max', 20000);
        $field->columnWidth = 100;
        $fieldset->add($field);
        $inputfields->add($fieldset);

        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = $this->_('Vox community (optional)');
        $fieldset->icon = 'comments';
        $fieldset->collapsed = Inputfield::collapsedYes;

        $field = $modules->get('InputfieldCheckbox');
        $field->attr('name', 'voxEnabled');
        $field->label = $this->_('Use published Vox community content');
        $field->description = $this->_('Lets Liora answer from published reviews, questions, replies and discussions attached to the current or retrieved page. Pending, spam and private data are never included.');
        if((bool)$this->setting('voxEnabled', true)) $field->attr('checked', 'checked');
        $fieldset->add($field);

        $field = $modules->get('InputfieldInteger');
        $field->attr('name', 'voxMaxEntries');
        $field->label = $this->_('Maximum community entries');
        $field->attr('value', (int)$this->setting('voxMaxEntries', 8));
        $field->attr('min', 1);
        $field->attr('max', 30);
        $field->columnWidth = 50;
        $fieldset->add($field);

        $field = $modules->get('InputfieldInteger');
        $field->attr('name', 'voxMaxContextChars');
        $field->label = $this->_('Maximum community context characters');
        $field->attr('value', (int)$this->setting('voxMaxContextChars', 5000));
        $field->attr('min', 500);
        $field->attr('max', 20000);
        $field->columnWidth = 50;
        $fieldset->add($field);
        $inputfields->add($fieldset);

        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = $this->_('CTA widget');
        $fieldset->icon = 'commenting';

        $field = $modules->get('InputfieldCheckbox');
        $field->attr('name', 'widgetEnabled');
        $field->label = $this->_('Allow the ready-made Liora chat widget to render');
        $field->description = $this->_(
            'This option does not insert Liora automatically. A template must call renderWidget() or render InputfieldLiora. '
            . 'Turn it off when using only ask(), chat(), complete(), or a custom integration; Liora Insights remains available.'
        );
        if((bool)$this->setting('widgetEnabled', true)) $field->attr('checked', 'checked');
        $fieldset->add($field);

        $field = $modules->get('InputfieldMarkup');
        $field->attr('name', 'integrationExamples');
        $field->label = $this->_('How to add Liora');
        $field->value = $this->integrationExamplesMarkup();
        $fieldset->add($field);

        $field = $modules->get('InputfieldCheckbox');
        $field->attr('name', 'localHistoryEnabled');
        $field->label = $this->_('Let visitors restore conversations from this browser');
        $field->description = $this->_('Conversation copies are stored in LocalStorage and loaded only when the visitor chooses one.');
        if((bool)$this->setting('localHistoryEnabled', true)) $field->attr('checked', 'checked');
        $fieldset->add($field);

        $field = $modules->get('InputfieldCheckbox');
        $field->attr('name', 'showWelcomeMessage');
        $field->label = $this->_('Show a welcome message in an empty conversation');
        if((bool)$this->setting('showWelcomeMessage', true)) $field->attr('checked', 'checked');
        $fieldset->add($field);

        $field = $modules->get('InputfieldCheckbox');
        $field->attr('name', 'showSuggestedPrompts');
        $field->label = $this->_('Show starter questions in an empty conversation');
        $field->description = $this->_('Up to three localized questions appear as buttons. Choosing one immediately starts a normal tracked conversation.');
        if((bool)$this->setting('showSuggestedPrompts', true)) $field->attr('checked', 'checked');
        $fieldset->add($field);

        $field = $modules->get('InputfieldCheckbox');
        $field->attr('name', 'showCopyButton');
        $field->label = $this->_('Show a copy action on chat messages');
        if((bool)$this->setting('showCopyButton', true)) $field->attr('checked', 'checked');
        $field->columnWidth = 34;
        $fieldset->add($field);

        $field = $modules->get('InputfieldCheckbox');
        $field->attr('name', 'showResponseTime');
        $field->label = $this->_('Show response time on Liora answers');
        if((bool)$this->setting('showResponseTime', true)) $field->attr('checked', 'checked');
        $field->columnWidth = 33;
        $fieldset->add($field);

        $field = $modules->get('InputfieldCheckbox');
        $field->attr('name', 'showTokenUsage');
        $field->label = $this->_('Show token usage on Liora answers');
        $field->description = $this->_('Token usage is shown only when the selected provider returns it.');
        if((bool)$this->setting('showTokenUsage', true)) $field->attr('checked', 'checked');
        $field->columnWidth = 33;
        $fieldset->add($field);

        $field = $modules->get('InputfieldSelect');
        $field->attr('name', 'widgetTheme');
        $field->label = $this->_('Widget color theme');
        $field->description = $this->_(
            'Adaptive follows the visitor’s operating-system light/dark preference and switches live. '
            . 'Choose Light or Dark only when the website forces one color scheme.'
        );
        foreach($this->themeOptions() as $value => $label) $field->addOption($value, $label);
        $field->attr('value', (string)$this->setting('widgetTheme', 'default'));
        $fieldset->add($field);

        $field = $modules->get('InputfieldInteger');
        $field->attr('name', 'localHistoryThreads');
        $field->label = $this->_('Conversations kept in this browser');
        $field->attr('value', (int)$this->setting('localHistoryThreads', 10));
        $field->attr('min', 1);
        $field->attr('max', 50);
        $fieldset->add($field);

        $field = $modules->get('InputfieldText');
        $field->attr('name', 'endpoint');
        $field->label = $this->_('JSON endpoint');
        $field->attr('value', (string)$this->setting('endpoint', '/agent/'));
        $fieldset->add($field);

        $preview = $modules->get('InputfieldLiora');
        if($preview) {
            $preview->attr('name', 'lioraPreview');
            $preview->label = $this->_('Preview');
            $preview->value = $this->_('a bottle, cocktail or pairing');
            $preview->previewOnly = true;
            $preview->collapsed = Inputfield::collapsedYes;
            $fieldset->add($preview);
        }
        $inputfields->add($fieldset);

        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = $this->_('Widget texts and localization');
        $fieldset->icon = 'language';
        $fieldset->collapsed = Inputfield::collapsedYes;

        $presets = $this->getWidgetTextPresets();
        if($presets) {
            $sanitizer = $this->wire('sanitizer');
            $languages = $this->wire('languages');
            $multiLanguage = $languages && $languages->count() > 1;
            $languageSelect = '';
            if($multiLanguage) {
                $languageSelect = "<p><label>" . $this->_('Apply presets to language:') . " <select class='liora-preset-lang'>";
                foreach($languages as $language) {
                    $value = $language->isDefault() ? '' : (string)$language->id;
                    $title = $sanitizer->entities($language->get('title|name'));
                    $languageSelect .= "<option value='{$value}'>{$title}</option>";
                }
                $languageSelect .= '</select></label></p>';
            }

            $buttons = '';
            foreach($presets as $code => $preset) {
                $buttons .= "<button type='button' class='ui-button ui-state-default liora-preset-btn' data-preset='"
                    . $sanitizer->entities($code) . "'><span class='ui-button-text'>"
                    . $sanitizer->entities($preset['_label']) . '</span></button> ';
            }
            $json = $sanitizer->entities(json_encode($presets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $markup = $modules->get('InputfieldMarkup');
            $markup->attr('name', 'widgetTextPresets');
            $markup->label = $this->_('Language presets');
            $markup->description = $this->_('Fill all visitor-facing widget texts with a ready-made translation, then edit the wording if needed.');
            if($multiLanguage) {
                $markup->description .= ' ' . $this->_('Choose the target ProcessWire language before applying a preset.');
            }
            $markup->value = "<div class='liora-presets' data-presets=\"{$json}\">{$languageSelect}{$buttons}</div>"
                . $this->widgetPresetScript();
            $fieldset->add($markup);
        }

        $textFields = [
            'widgetHeading' => [$this->_('Heading'), 'text', 50],
            'widgetIntro' => [$this->_('Introduction'), 'textarea', 50],
            'widgetPlaceholder' => [$this->_('Question placeholder'), 'text', 50],
            'welcomeMessage' => [$this->_('Welcome message'), 'textarea', 50],
            'suggestedPrompt1' => [$this->_('Starter question 1'), 'text', 34],
            'suggestedPrompt2' => [$this->_('Starter question 2'), 'text', 33],
            'suggestedPrompt3' => [$this->_('Starter question 3'), 'text', 33],
            'widgetSuggestionsLabel' => [$this->_('Starter questions accessible label'), 'text', 50],
            'privacyNotice' => [$this->_('Conversation quality notice'), 'textarea', 100],
            'widgetPrevious' => [$this->_('Button: previous conversations'), 'text', 34],
            'widgetNew' => [$this->_('Button: new conversation'), 'text', 33],
            'widgetExpand' => [$this->_('Button: expand conversation'), 'text', 33],
            'widgetCompact' => [$this->_('Button: compact conversation'), 'text', 34],
            'widgetEditTitle' => [$this->_('Label: edit title'), 'text', 33],
            'widgetSave' => [$this->_('Button: save'), 'text', 33],
            'widgetCancel' => [$this->_('Button: cancel'), 'text', 34],
            'widgetAsk' => [$this->_('Button: ask'), 'text', 33],
            'widgetAskLiora' => [$this->_('Question field label'), 'text', 33],
            'widgetThinking' => [$this->_('Thinking status'), 'text', 34],
            'widgetCopy' => [$this->_('Button: copy message'), 'text', 25],
            'widgetCopied' => [$this->_('Copy confirmation'), 'text', 25],
            'widgetResponseTime' => [$this->_('Response time label'), 'text', 25],
            'widgetTokens' => [$this->_('Token count label'), 'text', 25],
            'widgetSources' => [$this->_('Sources label'), 'text', 34],
            'widgetConversation' => [$this->_('Untitled conversation label'), 'text', 33],
            'widgetAiDisclaimer' => [$this->_('AI disclaimer'), 'textarea', 50],
            'widgetHistoryNotice' => [$this->_('Browser history notice'), 'textarea', 50],
            'widgetGenericError' => [$this->_('Generic error'), 'text', 34],
            'widgetEmptyError' => [$this->_('Empty answer error'), 'text', 33],
            'widgetConnectionError' => [$this->_('Connection error'), 'text', 33],
        ];
        $defaults = $this->widgetTextDefaults();
        foreach($textFields as $name => [$label, $type, $width]) {
            $field = $modules->get($type === 'textarea' ? 'InputfieldTextarea' : 'InputfieldText');
            $field->attr('name', $name);
            $field->label = $label;
            $field->useLanguages = true;
            if($type === 'textarea') $field->attr('rows', 3);
            $field->columnWidth = $width;
            $field->attr('value', (string)$this->setting($name, $defaults[$name] ?? ''));
            if($languages) {
                foreach($languages as $language) {
                    if($language->isDefault()) continue;
                    $field->set('value' . $language->id, (string)$this->get($name . '__' . $language->id));
                }
            }
            $fieldset->add($field);
        }
        $inputfields->add($fieldset);

        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = $this->_('Tracking and privacy');
        $fieldset->icon = 'line-chart';
        $fieldset->collapsed = Inputfield::collapsedYes;

        foreach([
            'maxQuestionLength' => [$this->_('Maximum question length'), 1000, 100, 5000],
            'requestsPerHour' => [$this->_('Questions per session per hour'), 20, 1, 200],
            'historyMessages' => [$this->_('Conversation messages sent as AI context'), 10, 2, 40],
        ] as $name => [$label, $default, $min, $max]) {
            $field = $modules->get('InputfieldInteger');
            $field->attr('name', $name);
            $field->label = $label;
            $field->attr('value', (int)$this->setting($name, $default));
            $field->attr('min', $min);
            $field->attr('max', $max);
            $field->columnWidth = 33;
            $fieldset->add($field);
        }

        $field = $modules->get('InputfieldCheckbox');
        $field->attr('name', 'showPrivacyNotice');
        $field->label = $this->_('Show the quality and privacy notice');
        if((bool)$this->setting('showPrivacyNotice', true)) $field->attr('checked', 'checked');
        $fieldset->add($field);

        $field = $modules->get('InputfieldCheckbox');
        $field->attr('name', 'deleteDataOnUninstall');
        $field->label = $this->_('Delete tracked questions when Liora is uninstalled');
        $field->description = $this->_('Keep this off unless the demand history should be permanently removed.');
        if((bool)$this->setting('deleteDataOnUninstall', false)) $field->attr('checked', 'checked');
        $fieldset->add($field);
        $inputfields->add($fieldset);

        return $inputfields;
    }

}
