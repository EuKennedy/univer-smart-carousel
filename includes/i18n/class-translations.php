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
			'GitHub'                 => 'GitHub',
			'Settings'               => 'Configurações',
			'Campaigns'              => 'Campanhas',

			// Admin — appbar / list
			'New'                    => 'Nova',
			'Search campaigns…'      => 'Buscar campanhas…',
			'No campaigns yet'       => 'Nenhuma campanha ainda',
			'Create your first carousel campaign to get a shareable shortcode.' => 'Crie sua primeira campanha de carrossel para gerar um shortcode compartilhável.',
			'Create campaign'        => 'Criar campanha',
			'Loading…'               => 'Carregando…',
			'Select a campaign'      => 'Selecione uma campanha',
			'Pick a campaign on the left, or create a new one.' => 'Escolha uma campanha à esquerda, ou crie uma nova.',
			'New campaign'           => 'Nova campanha',

			// Admin — editor topbar
			'Untitled campaign'      => 'Campanha sem título',
			'Delete'                 => 'Excluir',
			'Save changes'           => 'Salvar alterações',

			// Admin — campaign sidebar
			'Campaign'               => 'Campanha',
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
			'Univer Smart Carousel: this campaign is not currently live (status, start_date, or end_date).' => 'Univer Smart Carousel: esta campanha não está ativa no momento (status, data de início ou data de término).',

			// REST errors
			'Campaign not found.'    => 'Campanha não encontrada.',
			'Failed to create campaign.' => 'Falha ao criar campanha.',
			'Failed to delete campaign.' => 'Falha ao excluir campanha.',
		];
	}
}
