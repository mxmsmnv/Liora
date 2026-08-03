<?php namespace ProcessWire;

trait LioraLocalizationTrait {

    protected function widgetTextDefaults(): array {
        return [
            'widgetHeading' => 'Still looking? Ask Liora',
            'widgetIntro' => 'Tell me what you want to know. Your question also helps us improve this page.',
            'widgetPlaceholder' => 'Ask about this page, its content, products, or services…',
            'welcomeMessage' => 'Hi — I’m Liora. Ask me about this page or anything you could not find on the website.',
            'suggestedPrompt1' => 'Help me find the right information',
            'suggestedPrompt2' => 'Summarize this page',
            'suggestedPrompt3' => 'What do people think about this?',
            'widgetSuggestionsLabel' => 'Suggested questions',
            'privacyNotice' => 'Your questions help us improve this website and may be reviewed for quality. Please do not include personal details.',
            'widgetPrevious' => 'Previous conversations',
            'widgetNew' => 'New conversation',
            'widgetExpand' => 'Expand conversation',
            'widgetCompact' => 'Compact conversation',
            'widgetEditTitle' => 'Edit title',
            'widgetSave' => 'Save',
            'widgetCancel' => 'Cancel',
            'widgetAsk' => 'Ask',
            'widgetAskLiora' => 'Ask Liora',
            'widgetThinking' => 'Liora is thinking',
            'widgetCopy' => 'Copy',
            'widgetCopied' => 'Copied',
            'widgetResponseTime' => 'Response time',
            'widgetTokens' => 'tokens',
            'widgetSources' => 'Sources',
            'widgetConversation' => 'Conversation',
            'widgetAiDisclaimer' => 'AI can make mistakes. Please verify important information.',
            'widgetHistoryNotice' => 'Conversation history stays in this browser so you can return to it later.',
            'widgetGenericError' => 'Liora could not answer right now.',
            'widgetEmptyError' => 'Liora returned an empty answer.',
            'widgetConnectionError' => 'Connection error. Please try again.',
        ];
    }

    protected function widgetText(string $name): string {
        $defaults = $this->widgetTextDefaults();
        $default = (string)($defaults[$name] ?? '');
        $languages = $this->wire('languages');
        $user = $this->wire('user');
        if($languages && $user && $user->language && !$user->language->isDefault()) {
            $translated = trim((string)$this->get($name . '__' . $user->language->id));
            if($translated !== '') return $translated;
        }
        return (string)$this->setting($name, $default);
    }

    protected function getWidgetTextPresets(): array {
        $english = $this->widgetTextDefaults();
        return [
            'en' => ['_label' => 'English'] + $english,
            'de' => ['_label' => 'Deutsch',
                'widgetHeading' => 'Noch Fragen? Fragen Sie Liora', 'widgetIntro' => 'Sagen Sie uns, was Sie wissen möchten. Ihre Frage hilft uns auch, diese Seite zu verbessern.',
                'widgetPlaceholder' => 'Fragen Sie nach dieser Seite, ihren Inhalten, Produkten oder Dienstleistungen…', 'welcomeMessage' => 'Hallo — ich bin Liora. Fragen Sie mich zu dieser Seite oder zu etwas, das Sie auf der Website nicht gefunden haben.',
                'suggestedPrompt1' => 'Hilf mir, die richtigen Informationen zu finden', 'suggestedPrompt2' => 'Fasse diese Seite zusammen', 'suggestedPrompt3' => 'Was denkt die Community darüber?', 'widgetSuggestionsLabel' => 'Vorgeschlagene Fragen',
                'privacyNotice' => 'Ihre Fragen helfen uns, diese Website zu verbessern, und können zur Qualitätskontrolle geprüft werden. Bitte geben Sie keine persönlichen Daten an.',
                'widgetPrevious' => 'Frühere Gespräche', 'widgetNew' => 'Neues Gespräch', 'widgetExpand' => 'Gespräch erweitern', 'widgetCompact' => 'Gespräch verkleinern',
                'widgetEditTitle' => 'Titel bearbeiten', 'widgetSave' => 'Speichern', 'widgetCancel' => 'Abbrechen', 'widgetAsk' => 'Fragen', 'widgetAskLiora' => 'Liora fragen',
                'widgetThinking' => 'Liora denkt nach', 'widgetCopy' => 'Kopieren', 'widgetCopied' => 'Kopiert', 'widgetResponseTime' => 'Antwortzeit', 'widgetTokens' => 'Token',
                'widgetSources' => 'Quellen', 'widgetConversation' => 'Gespräch', 'widgetAiDisclaimer' => 'KI kann Fehler machen. Bitte prüfen Sie wichtige Informationen.',
                'widgetHistoryNotice' => 'Der Gesprächsverlauf bleibt in diesem Browser gespeichert, damit Sie später darauf zurückkommen können.',
                'widgetGenericError' => 'Liora kann gerade nicht antworten.', 'widgetEmptyError' => 'Liora hat eine leere Antwort zurückgegeben.', 'widgetConnectionError' => 'Verbindungsfehler. Bitte versuchen Sie es erneut.'],
            'fr' => ['_label' => 'Français',
                'widgetHeading' => 'Vous cherchez encore ? Demandez à Liora', 'widgetIntro' => 'Dites-nous ce que vous souhaitez savoir. Votre question nous aide aussi à améliorer cette page.',
                'widgetPlaceholder' => 'Posez une question sur cette page, son contenu, ses produits ou ses services…', 'welcomeMessage' => 'Bonjour, je suis Liora. Interrogez-moi sur cette page ou sur ce que vous n’avez pas trouvé sur le site.',
                'suggestedPrompt1' => 'Aidez-moi à trouver la bonne information', 'suggestedPrompt2' => 'Résumez cette page', 'suggestedPrompt3' => 'Qu’en pense la communauté ?', 'widgetSuggestionsLabel' => 'Questions suggérées',
                'privacyNotice' => 'Vos questions nous aident à améliorer ce site et peuvent être examinées pour le contrôle qualité. N’indiquez pas de données personnelles.',
                'widgetPrevious' => 'Conversations précédentes', 'widgetNew' => 'Nouvelle conversation', 'widgetExpand' => 'Agrandir la conversation', 'widgetCompact' => 'Réduire la conversation',
                'widgetEditTitle' => 'Modifier le titre', 'widgetSave' => 'Enregistrer', 'widgetCancel' => 'Annuler', 'widgetAsk' => 'Demander', 'widgetAskLiora' => 'Demander à Liora',
                'widgetThinking' => 'Liora réfléchit', 'widgetCopy' => 'Copier', 'widgetCopied' => 'Copié', 'widgetResponseTime' => 'Temps de réponse', 'widgetTokens' => 'jetons',
                'widgetSources' => 'Sources', 'widgetConversation' => 'Conversation', 'widgetAiDisclaimer' => 'L’IA peut se tromper. Vérifiez les informations importantes.',
                'widgetHistoryNotice' => 'L’historique reste dans ce navigateur afin que vous puissiez y revenir plus tard.',
                'widgetGenericError' => 'Liora ne peut pas répondre pour le moment.', 'widgetEmptyError' => 'Liora a renvoyé une réponse vide.', 'widgetConnectionError' => 'Erreur de connexion. Veuillez réessayer.'],
            'es' => ['_label' => 'Español',
                'widgetHeading' => '¿Aún buscas? Pregunta a Liora', 'widgetIntro' => 'Dinos qué quieres saber. Tu pregunta también nos ayuda a mejorar esta página.',
                'widgetPlaceholder' => 'Pregunta sobre esta página, su contenido, productos o servicios…', 'welcomeMessage' => 'Hola, soy Liora. Pregúntame sobre esta página o sobre algo que no hayas encontrado en el sitio.',
                'suggestedPrompt1' => 'Ayúdame a encontrar la información adecuada', 'suggestedPrompt2' => 'Resume esta página', 'suggestedPrompt3' => '¿Qué opina la comunidad?', 'widgetSuggestionsLabel' => 'Preguntas sugeridas',
                'privacyNotice' => 'Tus preguntas nos ayudan a mejorar este sitio y pueden revisarse para controlar la calidad. No incluyas datos personales.',
                'widgetPrevious' => 'Conversaciones anteriores', 'widgetNew' => 'Nueva conversación', 'widgetExpand' => 'Ampliar conversación', 'widgetCompact' => 'Reducir conversación',
                'widgetEditTitle' => 'Editar título', 'widgetSave' => 'Guardar', 'widgetCancel' => 'Cancelar', 'widgetAsk' => 'Preguntar', 'widgetAskLiora' => 'Preguntar a Liora',
                'widgetThinking' => 'Liora está pensando', 'widgetCopy' => 'Copiar', 'widgetCopied' => 'Copiado', 'widgetResponseTime' => 'Tiempo de respuesta', 'widgetTokens' => 'tokens',
                'widgetSources' => 'Fuentes', 'widgetConversation' => 'Conversación', 'widgetAiDisclaimer' => 'La IA puede cometer errores. Verifica la información importante.',
                'widgetHistoryNotice' => 'El historial permanece en este navegador para que puedas retomarlo más tarde.',
                'widgetGenericError' => 'Liora no puede responder ahora.', 'widgetEmptyError' => 'Liora devolvió una respuesta vacía.', 'widgetConnectionError' => 'Error de conexión. Inténtalo de nuevo.'],
            'it' => ['_label' => 'Italiano',
                'widgetHeading' => 'Cerchi ancora? Chiedi a Liora', 'widgetIntro' => 'Dicci cosa vuoi sapere. La tua domanda ci aiuta anche a migliorare questa pagina.',
                'widgetPlaceholder' => 'Chiedi informazioni su questa pagina, i suoi contenuti, prodotti o servizi…', 'welcomeMessage' => 'Ciao, sono Liora. Chiedimi di questa pagina o di qualcosa che non hai trovato nel sito.',
                'suggestedPrompt1' => 'Aiutami a trovare le informazioni giuste', 'suggestedPrompt2' => 'Riassumi questa pagina', 'suggestedPrompt3' => 'Cosa ne pensa la community?', 'widgetSuggestionsLabel' => 'Domande suggerite',
                'privacyNotice' => 'Le tue domande ci aiutano a migliorare questo sito e possono essere esaminate per il controllo qualità. Non inserire dati personali.',
                'widgetPrevious' => 'Conversazioni precedenti', 'widgetNew' => 'Nuova conversazione', 'widgetExpand' => 'Espandi conversazione', 'widgetCompact' => 'Riduci conversazione',
                'widgetEditTitle' => 'Modifica titolo', 'widgetSave' => 'Salva', 'widgetCancel' => 'Annulla', 'widgetAsk' => 'Chiedi', 'widgetAskLiora' => 'Chiedi a Liora',
                'widgetThinking' => 'Liora sta pensando', 'widgetCopy' => 'Copia', 'widgetCopied' => 'Copiato', 'widgetResponseTime' => 'Tempo di risposta', 'widgetTokens' => 'token',
                'widgetSources' => 'Fonti', 'widgetConversation' => 'Conversazione', 'widgetAiDisclaimer' => 'L’IA può commettere errori. Verifica le informazioni importanti.',
                'widgetHistoryNotice' => 'La cronologia resta in questo browser per poterla riprendere in seguito.',
                'widgetGenericError' => 'Liora non può rispondere in questo momento.', 'widgetEmptyError' => 'Liora ha restituito una risposta vuota.', 'widgetConnectionError' => 'Errore di connessione. Riprova.'],
            'nl' => ['_label' => 'Nederlands',
                'widgetHeading' => 'Nog niet gevonden? Vraag het Liora', 'widgetIntro' => 'Vertel wat je wilt weten. Je vraag helpt ons ook deze pagina te verbeteren.',
                'widgetPlaceholder' => 'Vraag naar deze pagina, de inhoud, producten of diensten…', 'welcomeMessage' => 'Hallo, ik ben Liora. Vraag me naar deze pagina of naar iets dat je niet op de website kon vinden.',
                'suggestedPrompt1' => 'Help me de juiste informatie te vinden', 'suggestedPrompt2' => 'Vat deze pagina samen', 'suggestedPrompt3' => 'Wat vindt de community hiervan?', 'widgetSuggestionsLabel' => 'Voorgestelde vragen',
                'privacyNotice' => 'Je vragen helpen ons deze website te verbeteren en kunnen voor kwaliteitscontrole worden bekeken. Deel geen persoonlijke gegevens.',
                'widgetPrevious' => 'Eerdere gesprekken', 'widgetNew' => 'Nieuw gesprek', 'widgetExpand' => 'Gesprek vergroten', 'widgetCompact' => 'Gesprek verkleinen',
                'widgetEditTitle' => 'Titel bewerken', 'widgetSave' => 'Opslaan', 'widgetCancel' => 'Annuleren', 'widgetAsk' => 'Vragen', 'widgetAskLiora' => 'Vraag Liora',
                'widgetThinking' => 'Liora denkt na', 'widgetCopy' => 'Kopiëren', 'widgetCopied' => 'Gekopieerd', 'widgetResponseTime' => 'Reactietijd', 'widgetTokens' => 'tokens',
                'widgetSources' => 'Bronnen', 'widgetConversation' => 'Gesprek', 'widgetAiDisclaimer' => 'AI kan fouten maken. Controleer belangrijke informatie.',
                'widgetHistoryNotice' => 'De gespreksgeschiedenis blijft in deze browser zodat je later kunt terugkeren.',
                'widgetGenericError' => 'Liora kan nu niet antwoorden.', 'widgetEmptyError' => 'Liora gaf een leeg antwoord.', 'widgetConnectionError' => 'Verbindingsfout. Probeer het opnieuw.'],
            'pl' => ['_label' => 'Polski',
                'widgetHeading' => 'Nadal szukasz? Zapytaj Liorę', 'widgetIntro' => 'Powiedz, czego chcesz się dowiedzieć. Twoje pytanie pomaga nam też ulepszać tę stronę.',
                'widgetPlaceholder' => 'Zapytaj o tę stronę, jej treść, produkty lub usługi…', 'welcomeMessage' => 'Cześć, jestem Liora. Zapytaj mnie o tę stronę lub o coś, czego nie udało Ci się znaleźć w serwisie.',
                'suggestedPrompt1' => 'Pomóż mi znaleźć właściwe informacje', 'suggestedPrompt2' => 'Podsumuj tę stronę', 'suggestedPrompt3' => 'Co sądzi o tym społeczność?', 'widgetSuggestionsLabel' => 'Sugerowane pytania',
                'privacyNotice' => 'Twoje pytania pomagają nam ulepszać ten serwis i mogą być przeglądane w celu kontroli jakości. Nie podawaj danych osobowych.',
                'widgetPrevious' => 'Poprzednie rozmowy', 'widgetNew' => 'Nowa rozmowa', 'widgetExpand' => 'Rozwiń rozmowę', 'widgetCompact' => 'Zwiń rozmowę',
                'widgetEditTitle' => 'Edytuj tytuł', 'widgetSave' => 'Zapisz', 'widgetCancel' => 'Anuluj', 'widgetAsk' => 'Zapytaj', 'widgetAskLiora' => 'Zapytaj Liorę',
                'widgetThinking' => 'Liora myśli', 'widgetCopy' => 'Kopiuj', 'widgetCopied' => 'Skopiowano', 'widgetResponseTime' => 'Czas odpowiedzi', 'widgetTokens' => 'tokenów',
                'widgetSources' => 'Źródła', 'widgetConversation' => 'Rozmowa', 'widgetAiDisclaimer' => 'AI może popełniać błędy. Sprawdź ważne informacje.',
                'widgetHistoryNotice' => 'Historia rozmów pozostaje w tej przeglądarce, aby można było wrócić do niej później.',
                'widgetGenericError' => 'Liora nie może teraz odpowiedzieć.', 'widgetEmptyError' => 'Liora zwróciła pustą odpowiedź.', 'widgetConnectionError' => 'Błąd połączenia. Spróbuj ponownie.'],
            'ru' => ['_label' => 'Русский',
                'widgetHeading' => 'Не нашли ответ? Спросите Лиору', 'widgetIntro' => 'Расскажите, что вы хотите узнать. Ваш вопрос также поможет нам улучшить эту страницу.',
                'widgetPlaceholder' => 'Спросите об этой странице, её материалах, товарах или услугах…', 'welcomeMessage' => 'Привет! Я Лиора. Спросите меня об этой странице или о том, чего вы не нашли на сайте.',
                'suggestedPrompt1' => 'Помоги найти нужную информацию', 'suggestedPrompt2' => 'Кратко перескажи эту страницу', 'suggestedPrompt3' => 'Что об этом думают другие?', 'widgetSuggestionsLabel' => 'Примеры вопросов',
                'privacyNotice' => 'Ваши вопросы помогают нам улучшать этот сайт и могут проверяться для контроля качества. Не указывайте личные данные.',
                'widgetPrevious' => 'Прошлые разговоры', 'widgetNew' => 'Новый разговор', 'widgetExpand' => 'Развернуть разговор', 'widgetCompact' => 'Свернуть разговор',
                'widgetEditTitle' => 'Изменить название', 'widgetSave' => 'Сохранить', 'widgetCancel' => 'Отмена', 'widgetAsk' => 'Спросить', 'widgetAskLiora' => 'Спросить Лиору',
                'widgetThinking' => 'Лиора думает', 'widgetCopy' => 'Копировать', 'widgetCopied' => 'Скопировано', 'widgetResponseTime' => 'Время ответа', 'widgetTokens' => 'токенов',
                'widgetSources' => 'Источники', 'widgetConversation' => 'Разговор', 'widgetAiDisclaimer' => 'ИИ может ошибаться. Проверяйте важную информацию.',
                'widgetHistoryNotice' => 'История разговоров хранится в этом браузере, чтобы вы могли вернуться к ней позже.',
                'widgetGenericError' => 'Лиора сейчас не может ответить.', 'widgetEmptyError' => 'Лиора вернула пустой ответ.', 'widgetConnectionError' => 'Ошибка соединения. Попробуйте ещё раз.'],
        ];
    }

    protected function widgetPresetScript(): string {
        return <<<'JS'
<script>
(function(){
    var wrap = document.querySelector('.liora-presets');
    if(!wrap || wrap.dataset.bound) return;
    wrap.dataset.bound = '1';
    var presets = JSON.parse(wrap.getAttribute('data-presets'));
    var language = wrap.querySelector('.liora-preset-lang');
    function fieldName(key){
        var id = language ? language.value : '';
        return id ? key + '__' + id : key;
    }
    function note(text){
        var item = wrap.querySelector('.liora-preset-note');
        if(!item) {
            item = document.createElement('div');
            item.className = 'liora-preset-note';
            item.style.cssText = 'margin-top:8px;color:#059669';
            wrap.appendChild(item);
        }
        item.textContent = text;
    }
    wrap.addEventListener('click', function(event){
        var button = event.target.closest('.liora-preset-btn');
        if(!button) return;
        event.preventDefault();
        var data = presets[button.getAttribute('data-preset')];
        if(!data) return;
        var count = 0;
        Object.keys(data).forEach(function(key){
            if(key === '_label') return;
            var name = fieldName(key);
            var input = document.querySelector('#Inputfield_' + name) || document.querySelector('[name="' + name + '"]');
            if(input && 'value' in input) {
                input.value = data[key];
                input.dispatchEvent(new Event('input', {bubbles: true}));
                count++;
            }
        });
        note(count + ' fields filled — remember to Submit.');
        button.blur();
    });
})();
</script>
JS;
    }

}
