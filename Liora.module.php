<?php namespace ProcessWire;

require_once __DIR__ . '/LioraStore.php';
require_once __DIR__ . '/src/Core/LifecycleTrait.php';
require_once __DIR__ . '/src/AI/ServiceApiTrait.php';
require_once __DIR__ . '/src/Http/EndpointTrait.php';
require_once __DIR__ . '/src/Widget/RenderTrait.php';
require_once __DIR__ . '/src/Config/ConfigUiTrait.php';
require_once __DIR__ . '/src/Conversation/ConversationTrait.php';
require_once __DIR__ . '/src/Retrieval/AtlasTrait.php';
require_once __DIR__ . '/src/Retrieval/ContextTrait.php';
require_once __DIR__ . '/src/Support/PresentationTrait.php';
require_once __DIR__ . '/src/Support/MessageTrait.php';
require_once __DIR__ . '/src/Localization/LocalizationTrait.php';
require_once __DIR__ . '/src/Support/SettingsTrait.php';

/**
 * Liora — conversational assistance and visitor-demand analytics for ProcessWire.
 *
 * Liora turns an unsuccessful search or unanswered page question into a useful
 * answer and a structured demand signal. Squad remains responsible for
 * credentials and provider transport.
 *
 * @version 1.15.0
 */
class Liora extends WireData implements Module, ConfigurableModule {

    use LioraLifecycleTrait;
    use LioraServiceApiTrait;
    use LioraEndpointTrait;
    use LioraWidgetRenderTrait;
    use LioraConfigUiTrait;
    use LioraConversationTrait;
    use LioraAtlasTrait;
    use LioraContextTrait;
    use LioraPresentationTrait;
    use LioraMessageTrait;
    use LioraLocalizationTrait;
    use LioraSettingsTrait;

    protected ?LioraStore $storeInstance = null;
    protected static bool $assetsRendered = false;

    public static function getModuleInfo(): array {
        return [
            'title' => 'Liora',
            'version' => 1150,
            'summary' => 'AI answer CTA with optional Atlas RAG, Vox community context and content-demand analytics.',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/Liora',
            'icon' => 'comments',
            'singular' => true,
            'autoload' => false,
            'requires' => ['ProcessWire>=3.0.210', 'PHP>=8.1', 'Squad'],
            'installs' => ['InputfieldLiora', 'ProcessLiora'],
        ];
    }
}
