<?php
/**
 * Plugin translations.
 *
 * We don't ship .mo files. Instead the dictionary lives in PHP as a plain
 * associative array — same translations are reused for both the PHP
 * `__()` calls (via the `gettext` filter) and for the React admin (via
 * `setLocaleData` on `window.USC_CFG.translations`).
 *
 * Why no .mo:
 *  - Generating .mo locally requires `msgfmt`, which is friction in CI.
 *  - The dictionary is small (~80 strings) and won't grow huge.
 *  - One source of truth keeps PHP and JS in sync.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\I18n;

use Univer\SmartCarousel\Database\Settings_Repository;

defined( 'ABSPATH' ) || exit;

final class Translations {

	private const TEXT_DOMAIN = 'univer-smart-carousel';

	public function __construct() {
		add_filter( 'gettext', [ $this, 'translate' ], 10, 3 );
		add_filter( 'gettext_with_context', [ $this, 'translate_with_context' ], 10, 4 );
	}

	/**
	 * gettext filter — substitute strings if the user has selected pt-br.
	 */
	public function translate( $translation, $text, $domain ) {
		if ( self::TEXT_DOMAIN !== $domain ) {
			return $translation;
		}
		if ( ! self::is_pt_br() ) {
			return $translation;
		}
		$dictionary = self::dictionary();
		return $dictionary[ $text ] ?? $translation;
	}

	public function translate_with_context( $translation, $text, $context, $domain ) {
		return $this->translate( $translation, $text, $domain );
	}

	/**
	 * Returns the translation dictionary in the wp.i18n / Jed JSON shape so
	 * the admin React app can `setLocaleData` it directly.
	 *
	 * @return array<string,mixed>
	 */
	public static function js_locale_data(): array {
		if ( ! self::is_pt_br() ) {
			return [];
		}

		$messages = [
			'' => [
				'domain'       => self::TEXT_DOMAIN,
				'lang'         => 'pt_BR',
				'plural-forms' => 'nplurals=2; plural=(n > 1);',
			],
		];

		foreach ( self::dictionary() as $source => $translation ) {
			$messages[ $source ] = [ $translation ];
		}

		return [
			'domain'      => self::TEXT_DOMAIN,
			'locale_data' => [
				self::TEXT_DOMAIN => $messages,
			],
		];
	}

	public static function current_language(): string {
		return Settings_Repository::get( 'language', Settings_Repository::LANGUAGE_EN );
	}

	private static function is_pt_br(): bool {
		return Settings_Repository::LANGUAGE_PT_BR === self::current_language();
	}

	/**
	 * The full English → pt-BR dictionary.
	 *
	 * Keep keys in sync with what's actually passed to `__()` in the codebase.
	 * Adding a new English string anywhere? Add it here too.
	 *
	 * @return array<string,string>
	 */
	private static function dictionary(): array {
		return [
			// Plugin chrome
			'Smart Carousel'         => 'Carrossel Inteligente',
			'Open'                   => 'Abrir',
			'Settings'               => 'Configurações',
			'Campaigns'              => 'Carrosseis',

			// Admin — appbar / list
			'New'                    => 'Novo',
			'Search campaigns…'      => 'Buscar carrosseis…',
			'No campaigns yet'       => 'Nenhum carrossel ainda',
			'Create your first carousel campaign to get a shareable shortcode.' => 'Crie seu primeiro carrossel para gerar um shortcode compartilhável.',
			'Create campaign'        => 'Criar carrossel',
			'Loading…'               => 'Carregando…',
			'Select a campaign'      => 'Selecione um carrossel',
			'Pick a campaign on the left, or create a new one.' => 'Escolha um carrossel à esquerda, ou crie um novo.',
			'New campaign'           => 'Novo carrossel',

			// Admin — editor topbar
			'Untitled campaign'      => 'Carrossel sem título',
			'Delete'                 => 'Excluir',
			'Save changes'           => 'Salvar alterações',

			// Admin — campaign sidebar
			'Campaign'               => 'Carrossel',
			'Status'                 => 'Status',
			'Draft'                  => 'Rascunho',
			'Active'                 => 'Ativa',
			'Paused'                 => 'Pausada',
			'Slug'                   => 'Slug',
			'Used in the shortcode. Letters, numbers, dashes only.' => 'Usado no shortcode. Apenas letras, números e traços.',
			'Start date'             => 'Data de início',
			'End date'               => 'Data de término',

			// Admin — layout section
			'Layout'                 => 'Layout',
			'Slides per view (desktop)' => 'Slides por visualização (desktop)',
			'Slides per view (mobile)'  => 'Slides por visualização (mobile)',
			'Aspect ratio (desktop)' => 'Proporção (desktop)',
			'Aspect ratio (mobile)'  => 'Proporção (mobile)',
			'Auto — match image'     => 'Automático — usar a proporção da imagem',
			'e.g. auto, 16/9, 1560x1080' => 'ex.: auto, 16/9, 1560x1080',
			'Preset ratios'          => 'Proporções predefinidas',
			'0 = sharp corners.'     => '0 = cantos retos.',
			'Gap (px)'               => 'Espaçamento (px)',
			'Border radius (px)'     => 'Borda arredondada (px)',

			// Admin — behavior section
			'Behavior'               => 'Comportamento',
			'Autoplay'               => 'Reprodução automática',
			'Disabled automatically for users with reduced-motion preference.' => 'Desativada automaticamente para usuários com preferência por reduzir animações.',
			'Autoplay delay (ms)'    => 'Intervalo de reprodução (ms)',
			'Loop'                   => 'Loop',
			'Navigation'             => 'Navegação',
			'How visitors move between slides.' => 'Como os visitantes navegam entre os slides.',
			'None'                   => 'Nenhuma',
			'Dots'                   => 'Pontos',
			'Arrows'                 => 'Setas',
			'Show progress bar'      => 'Exibir barra de progresso',
			'Pause on hover'         => 'Pausar ao passar o mouse',
			'Slide'                  => 'Deslizar',
			'Fade'                   => 'Esmaecer',

			// Admin — banner editor
			'Desktop'                => 'Desktop',
			'Mobile'                 => 'Mobile',
			'Desktop banners'        => 'Banners para desktop',
			'Mobile banners'         => 'Banners para mobile',
			'Drag a card to reorder. Click an image to replace.' => 'Arraste um cartão para reordenar. Clique em uma imagem para substituí-la.',
			'Add banners'            => 'Adicionar banners',
			'No banners added yet.'  => 'Nenhum banner adicionado ainda.',
			'Pick from media library' => 'Escolher da biblioteca de mídia',
			'Select desktop banners' => 'Selecionar banners para desktop',
			'Select mobile banners'  => 'Selecionar banners para mobile',
			'Replace banner'         => 'Substituir banner',
			'Replace image'          => 'Substituir imagem',
			'No image'               => 'Sem imagem',
			'Destination URL'        => 'URL de destino',
			'Open link in'           => 'Abrir link em',
			'Same tab'               => 'Mesma aba',
			'New tab'                => 'Nova aba',
			'Alt text'               => 'Texto alternativo',
			'Describe the banner…'   => 'Descreva o banner…',
			'Remove banner'          => 'Remover banner',

			// Admin — shortcode panel
			'Embed'                  => 'Inserir',
			'Shortcodes'             => 'Shortcodes',
			'Paste these anywhere on your site. The carousel will only render while the campaign is Active.' => 'Cole-os onde quiser no seu site. O carrossel só será exibido enquanto a campanha estiver Ativa.',
			'Copy'                   => 'Copiar',
			'Copied!'                => 'Copiado!',

			// Admin — toasts and confirmations
			'Campaign saved.'        => 'Campanha salva.',
			'Save failed.'           => 'Falha ao salvar.',
			'Campaign deleted.'      => 'Campanha excluída.',
			'Delete failed.'         => 'Falha ao excluir.',
			'Failed to load campaigns.' => 'Falha ao carregar campanhas.',
			'Failed to load campaign.'  => 'Falha ao carregar campanha.',
			'Delete this campaign?'  => 'Excluir esta campanha?',
			'This will remove the campaign and all of its banners. The shortcode will stop rendering anything.' => 'Isto removerá a campanha e todos os seus banners. O shortcode deixará de exibir qualquer conteúdo.',
			'Cancel'                 => 'Cancelar',
			'Delete permanently'     => 'Excluir permanentemente',
			'Close'                  => 'Fechar',

			// Admin — settings page
			'Plugin-wide configuration. These apply to every campaign.' => 'Configurações globais do plugin. Aplicam-se a todas as campanhas.',
			'Save settings'          => 'Salvar configurações',
			'Settings saved.'        => 'Configurações salvas.',
			'Failed to load settings.' => 'Falha ao carregar configurações.',
			'General'                => 'Geral',
			'Language'               => 'Idioma',
			'Used in the admin and on the carousel rendered on your site. Independent of your WordPress site language.' => 'Usado no admin e no carrossel exibido no seu site. Independente do idioma do seu site WordPress.',
			'Plugin language'        => 'Idioma do plugin',
			'English'                => 'Inglês',
			'Português (Brasil)'     => 'Português (Brasil)',
			'Reload the admin page after saving to see the new language take effect.' => 'Recarregue a página do admin após salvar para ver o novo idioma em efeito.',

			// Frontend — accessibility / chrome
			'Previous slide'         => 'Slide anterior',
			'Next slide'             => 'Próximo slide',
			'Choose slide'           => 'Escolher slide',
			'Open promotion'         => 'Abrir promoção',
			'%1$d of %2$d'           => '%1$d de %2$d',
			'Univer Smart Carousel: this campaign is not currently live (status, start_date, or end_date).' => 'Univer Smart Carousel: este carrossel não está ativo no momento (status, data de início ou data de término).',

			// Grouped banner editor
			'Desktop groups'         => 'Grupos para desktop',
			'Mobile groups'          => 'Grupos para mobile',
			'Each group can be paused without losing its banners. Toggle the switch on the group header to hide a whole sub-campaign.' => 'Cada grupo pode ser pausado sem perder seus banners. Use o switch no cabeçalho do grupo para ocultar uma sub-campanha inteira.',
			'New group'              => 'Novo grupo',
			'No groups yet — create one to start adding banners.' => 'Nenhum grupo ainda — crie um para começar a adicionar banners.',
			'Save the carousel first, then add groups.' => 'Salve o carrossel primeiro e depois adicione grupos.',
			'Toggle group'           => 'Alternar grupo',
			'%d banners'             => '%d banners',
			'Banners'                => 'Banners',
			'Delete group'           => 'Excluir grupo',
			'No banners in this group yet.' => 'Nenhum banner neste grupo ainda.',
			'Active'                 => 'Ativo',
			'Failed to create group.' => 'Falha ao criar grupo.',
			'Failed to toggle group.' => 'Falha ao alternar grupo.',
			'Failed to toggle banner.' => 'Falha ao alternar banner.',
			'Failed to delete group.' => 'Falha ao excluir grupo.',
			'Failed to save.'         => 'Falha ao salvar.',
			'Failed to add banner.'   => 'Falha ao adicionar banner.',
			'Failed to refresh.'      => 'Falha ao atualizar.',
			'Failed to reorder.'      => 'Falha ao reordenar.',
			'Drag to reorder'         => 'Arraste para reordenar',
			'Banner name (optional)'  => 'Nome do banner (opcional)',
			'Internal label — e.g. "Black Friday hero"' => 'Rótulo interno — ex.: "Hero Black Friday"',
			'Duplicate banner'        => 'Duplicar banner',
			'Banner duplicated.'      => 'Banner duplicado.',
			'Failed to duplicate banner.' => 'Falha ao duplicar banner.',
			'Replace banner image'    => 'Substituir imagem do banner',
			'Click to replace image'  => 'Clique para substituir a imagem',
			'Failed to replace image.' => 'Falha ao substituir imagem.',

			// Shared media-picker labels (used across banner editors)
			'No image'                => 'Sem imagem',
			'Replace image'           => 'Substituir imagem',
			'Pick from media library' => 'Escolher da biblioteca de mídia',

			// Image optimization card
			'Performance'            => 'Desempenho',
			'Image optimization'     => 'Otimização de imagens',
			'Resize, recompress, and serve WebP. The first render after you change a setting generates the new variants; subsequent views are cached.' => 'Redimensiona, recomprime e serve WebP. Ao mudar uma configuração, a primeira renderização gera as novas variantes; as próximas são servidas do cache.',
			'Optimize images for this carousel' => 'Otimizar imagens deste carrossel',
			'When off, the original upload is served as-is.' => 'Quando desligado, o arquivo original é servido sem alterações.',
			'JPEG quality'           => 'Qualidade JPEG',
			'40 = aggressive, 82 = default, 95 = near-lossless.' => '40 = agressivo, 82 = padrão, 95 = praticamente sem perdas.',
			'Max width (desktop)'    => 'Largura máxima (desktop)',
			'Max width (mobile)'     => 'Largura máxima (mobile)',
			'px'                     => 'px',
			'Serve WebP when the browser supports it' => 'Servir WebP quando o navegador suportar',
			'Smaller than JPEG at the same visual quality. Host must support WebP encoding.' => 'Menor que JPEG mantendo a mesma qualidade visual. O host precisa conseguir gerar WebP.',
			'Group deleted.'          => 'Grupo excluído.',
			'Rename group'            => 'Renomear grupo',
			'Group name'              => 'Nome do grupo',
			'Save'                    => 'Salvar',
			'Delete group "%s"?'      => 'Excluir grupo "%s"?',
			'All banners inside this group will be deleted too. If you just want to hide them temporarily, use the toggle on the group header instead.' => 'Todos os banners deste grupo serão excluídos também. Se quiser apenas ocultar temporariamente, use o switch no cabeçalho do grupo.',
			'Delete banner?'          => 'Excluir banner?',
			'The banner will be removed from this group. The image stays in your media library.' => 'O banner será removido deste grupo. A imagem continua na sua biblioteca de mídia.',

			// REST errors
			'Campaign not found.'    => 'Carrossel não encontrado.',
			'Failed to create campaign.' => 'Falha ao criar carrossel.',
			'Failed to delete campaign.' => 'Falha ao excluir carrossel.',
			'Banner not found.'      => 'Banner não encontrado.',
			'Group not found.'       => 'Grupo não encontrado.',
			'Failed to create group.' => 'Falha ao criar grupo.',
			'Failed to add banner. Make sure the group exists and image_id is valid.' => 'Falha ao adicionar banner. Confirme que o grupo existe e que o image_id é válido.',
			'Failed to update banner.' => 'Falha ao atualizar banner.',
			'Failed to create API key.' => 'Falha ao criar chave de API.',
			'API key not found.'     => 'Chave de API não encontrada.',
			'Authentication required. Send an Authorization: Bearer token, or sign in as an administrator.' => 'Autenticação obrigatória. Envie um header Authorization: Bearer ou entre como administrador.',
			'This API key does not have %s scope.' => 'Esta chave de API não tem permissão %s.',

			// API keys card
			'Integrations'           => 'Integrações',
			'API Keys'               => 'Chaves de API',
			'Use these keys to control the plugin programmatically — from AI agents, Zapier, custom scripts, anywhere. Send the key as Authorization: Bearer …' => 'Use essas chaves para controlar o plugin programaticamente — em agentes de IA, Zapier, scripts customizados, onde quiser. Envie a chave como Authorization: Bearer …',
			'New key'                => 'Nova chave',
			'No API keys yet'        => 'Nenhuma chave de API ainda',
			'Create your first key to give an AI or external system access to this plugin.' => 'Crie sua primeira chave para dar a uma IA ou sistema externo acesso a este plugin.',
			'Name'                   => 'Nome',
			'Key'                    => 'Chave',
			'Scope'                  => 'Permissão',
			'Last used'              => 'Último uso',
			'Actions'                => 'Ações',
			'Revoke'                 => 'Revogar',
			'Revoked'                => 'Revogada',
			'Read only — GET requests' => 'Somente leitura — apenas requisições GET',
			'Read + Write — full control' => 'Leitura + Escrita — controle total',
			'New API key'            => 'Nova chave de API',
			'Where will this key be used? e.g. "ChatGPT — Black Friday rollout".' => 'Onde essa chave será usada? Ex.: "ChatGPT — campanha de Black Friday".',
			'Generate key'           => 'Gerar chave',
			'Copy your new API key'  => 'Copie sua nova chave de API',
			'This is the only time you will see this key. Store it somewhere safe — if you lose it, you will need to generate a new one.' => 'Esta é a única vez que você verá essa chave. Guarde em local seguro — se perder, será preciso gerar outra.',
			'I copied it'            => 'Já copiei',
			'Send it as a Bearer token:' => 'Envie como token Bearer:',
			'Delete %s?'             => 'Excluir %s?',
			'Any integration using this key will stop working immediately. You can revoke instead if you want to keep the audit trail.' => 'Qualquer integração usando essa chave vai parar de funcionar imediatamente. Você pode revogar em vez disso se quiser manter o histórico.',
			'Key revoked.'           => 'Chave revogada.',
			'Key deleted.'           => 'Chave excluída.',
			'Failed to load API keys.' => 'Falha ao carregar chaves de API.',
			'Failed to revoke.'      => 'Falha ao revogar.',
			'Failed to delete.'      => 'Falha ao excluir.',
			'Delete key'             => 'Excluir chave',
			'Copy this key now — it will not be shown again.' => 'Copie esta chave agora — ela não será exibida novamente.',
		];
	}
}
