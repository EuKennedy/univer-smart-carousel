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

			// Mosaics tab
			'Mosaics'                 => 'Mosaicos',
			'Mosaic'                  => 'Mosaico',
			'New mosaic'              => 'Novo mosaico',
			'Create mosaic'           => 'Criar mosaico',
			'Select a mosaic'         => 'Selecione um mosaico',
			'Pick a mosaic on the left, or create a new one.' => 'Escolha um mosaico à esquerda, ou crie um novo.',
			'Untitled mosaic'         => 'Mosaico sem título',
			'Search mosaics…'         => 'Buscar mosaicos…',
			'No mosaics yet'          => 'Nenhum mosaico ainda',
			'Create your first mosaic to get a shareable shortcode.' => 'Crie seu primeiro mosaico para gerar um shortcode compartilhável.',
			'Delete this mosaic?'     => 'Excluir este mosaico?',
			'This will remove the mosaic and all of its items. The shortcode will stop rendering anything.' => 'Isso removerá o mosaico e todos os seus itens. O shortcode deixará de exibir qualquer conteúdo.',
			'Mosaic saved.'           => 'Mosaico salvo.',
			'Mosaic deleted.'         => 'Mosaico excluído.',
			'Failed to load mosaics.' => 'Falha ao carregar mosaicos.',
			'Failed to load mosaic.'  => 'Falha ao carregar mosaico.',
			'Items'                   => 'Itens',
			'Item name (optional)'    => 'Nome do item (opcional)',
			'Internal label — e.g. "Revendedor oficial"' => 'Rótulo interno — ex.: "Revendedor oficial"',
			'Drag to reorder. Click a thumbnail to replace the image. Use Col / Row to make a cell bigger (bento layout).' => 'Arraste para reordenar. Clique em uma imagem para substituí-la. Use Col / Row para fazer uma célula ocupar mais espaço (layout bento).',
			'Drag to reorder. Click a thumbnail to replace the image. The chosen Format handles sizes for you.' => 'Arraste para reordenar. Clique em uma imagem para substituí-la. O Formato escolhido cuida dos tamanhos pra você.',
			// Layout presets
			'Format'                    => 'Formato',
			'Format (desktop)'          => 'Formato (desktop)',
			'Format (mobile)'           => 'Formato (mobile)',
			'Desktop and mobile pick their format independently — pair a wide hero on desktop with a stacked hero on mobile, or any mix.' => 'Desktop e mobile escolhem o formato de forma independente — combine um hero largo no desktop com um hero empilhado no mobile, ou qualquer mistura.',
			'Hero on top'               => 'Destaque em cima',
			'First item full-width, the rest are equal cards below.' => 'Primeiro item em largura total; os demais ficam como cards iguais embaixo.',
			'Hero at the bottom'        => 'Destaque embaixo',
			'Equal cards on top, the last item takes the full bottom row.' => 'Cards iguais em cima; o último item ocupa toda a linha de baixo.',
			'Featured on the left'      => 'Destaque à esquerda',
			'First item is a 2×2 feature, the rest stack on the right.' => 'O primeiro item vira um destaque 2×2; os demais se empilham à direita.',
			'Featured on the right'     => 'Destaque à direita',
			'Last item is a 2×2 feature, the rest stack on the left.' => 'O último item vira um destaque 2×2; os demais se empilham à esquerda.',
			'Alternating rhythm'        => 'Ritmo alternado',
			'Full-width band, then two small cards, repeating.' => 'Faixa em largura total, depois dois cards menores, repetindo.',
			'Side by side'              => 'Lado a lado',
			'Images line up in equal-size cards, side by side — [] [] [].' => 'As imagens ficam em cards iguais, lado a lado — [] [] [].',
			// Cache section
			'Cache'                     => 'Cache',
			'Every save already triggers an auto-purge of WP Rocket / LiteSpeed / W3 Total / SiteGround / NGINX / OPcache and every other cache plugin we can detect. Use this as a manual panic button if a CDN in front of WordPress is still serving stale HTML.' => 'Toda vez que você salva, o plugin já purga automaticamente WP Rocket / LiteSpeed / W3 Total / SiteGround / NGINX / OPcache e qualquer outro cache que ele detectar. Use o botão abaixo como pânico manual se algum CDN na frente do WordPress ainda estiver servindo HTML antigo.',
			'Flush every cache now'     => 'Limpar todos os caches agora',
			'Every cache we could reach has been flushed.' => 'Todos os caches acessíveis foram limpos.',
			'Cache flush failed.'       => 'Falha ao limpar os caches.',
			'External CDNs with API-based invalidation (like Cloudflare) need their own credentials — hook the `usc_cache_purge` action to wire them in.' => 'CDNs externos com invalidação por API (como Cloudflare) precisam das próprias credenciais — conecte no action `usc_cache_purge` para integrá-los.',
			'You must be signed in to flush caches.' => 'Você precisa estar logado para limpar os caches.',
			'Only administrators can flush caches.' => 'Somente administradores podem limpar os caches.',
			'Free (manual)'             => 'Livre (manual)',
			'Set col / row span on every item yourself.' => 'Você define col / row span em cada item manualmente.',
			'Select mosaic images'    => 'Selecionar imagens do mosaico',
			'Replace item image'      => 'Substituir imagem do item',
			'Delete item?'            => 'Excluir item?',
			'The item will be removed from this mosaic. The image stays in your media library.' => 'O item será removido deste mosaico. A imagem continua na sua biblioteca de mídia.',
			'Duplicate item'          => 'Duplicar item',
			'Delete item'             => 'Excluir item',
			'Item duplicated.'        => 'Item duplicado.',
			'Failed to duplicate.'    => 'Falha ao duplicar.',
			'Failed to delete item.'  => 'Falha ao excluir item.',
			'Failed to add item.'     => 'Falha ao adicionar item.',
			'Failed to toggle.'       => 'Falha ao alternar.',
			'No items yet — add one to start building the grid.' => 'Nenhum item ainda — adicione um para começar a montar o grid.',
			'Save the mosaic first, then add items.' => 'Salve o mosaico primeiro e depois adicione itens.',
			'Click to replace image'  => 'Clique para substituir a imagem',
			'Describe the image…'     => 'Descreva a imagem…',
			'Col span (1–%d)'         => 'Col span (1–%d)',
			'Row span'                => 'Row span',
			'Aspect'                  => 'Proporção',
			'Columns (desktop)'       => 'Colunas (desktop)',
			'Columns (mobile)'        => 'Colunas (mobile)',
			'Optimize images for this mosaic' => 'Otimizar imagens deste mosaico',
			'Univer Smart Carousel: this mosaic is not currently live (status is not "active").' => 'Univer Smart Carousel: este mosaico não está ativo no momento (status diferente de "active").',

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
