<?php
/**
 * Self-documenting schema endpoint.
 *
 * `GET /usc/v1/discover` returns a single JSON document describing the
 * whole API surface — endpoints, request shapes, response shapes,
 * enums, examples. Built so that an LLM (or a developer) can read it
 * once and know how to drive the plugin without scanning code.
 *
 * Why a custom shape instead of OpenAPI 3.0:
 *   - 1/4 the size, no $ref indirection, examples inline.
 *   - LLMs ingest it in one pass; humans skim it in 30 seconds.
 *   - Easier to keep in sync with reality — it's hand-written here
 *     and lives next to the controllers it describes.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Rest_Api\V1;

use Univer\SmartCarousel\Database\Campaign_Repository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Discover_Controller {

	private string $namespace = USC_REST_NAMESPACE;
	private string $rest_base = 'discover';

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'discover' ],
				// Public — discovery is the entry point. Doesn't reveal
				// any data, just structure.
				'permission_callback' => '__return_true',
			]
		);
	}

	public function discover( WP_REST_Request $request ) {
		$base_url = rest_url( USC_REST_NAMESPACE );

		$schema = [
			'plugin'      => 'Univer Smart Carousel',
			'version'     => USC_VERSION,
			'description' => 'Lightweight, premium banner carousel for WordPress + WooCommerce. Marketing teams ship campaigns; integrations and AI agents drive everything via this API.',
			'author'      => [
				'name' => 'Kennedy Rodrigues Gomes Teixeira',
				'url'  => 'https://github.com/EuKennedy/univer-smart-carousel',
			],
			'base_url'    => $base_url,

			'auth' => [
				'type'        => 'bearer',
				'header'      => 'Authorization: Bearer <usc_live_…>',
				'how_to_get'  => 'In WP admin → Smart Carousel → Settings → API Keys → New key.',
				'scopes'      => [
					'read'  => 'GET / HEAD requests only.',
					'write' => 'Read plus POST / PUT / DELETE.',
				],
				'note'        => 'Cookie + nonce auth from inside wp-admin also works for browser-based callers.',
			],

			'concepts' => [
				'carousel'  => 'Top-level container. The plugin internally calls this a "campaign" (and the original /campaigns endpoints still work) — both names address the same resource. Each carousel exposes two shortcodes — one for desktop, one for mobile.',
				'group'     => 'A named bucket of banners inside a carousel. Scoped to a single device. Has its own is_active toggle so marketing can pause a sub-campaign without losing banners.',
				'banner'    => 'One image inside a group. Has a destination URL, alt text, sort order within the group, and its own is_active toggle.',
				'shortcode' => 'Two per carousel: [carouseldesktop_<slug>] and [carouselmobile_<slug>]. Render only while the carousel status === "active" AND the banner\'s group is active AND the banner itself is active.',
			],

			'models' => [
				'campaign' => $this->describe_campaign(),
				'group'    => $this->describe_group(),
				'banner'   => $this->describe_banner(),
				'settings' => $this->describe_settings(),
				'api_key'  => $this->describe_api_key(),
			],

			'endpoints' => $this->describe_endpoints( $base_url ),

			'examples' => [
				'curl_list_campaigns' => sprintf(
					"curl -H 'Authorization: Bearer usc_live_xxx' %s/campaigns",
					$base_url
				),
				'curl_create_campaign' => sprintf(
					"curl -X POST -H 'Authorization: Bearer usc_live_xxx' -H 'Content-Type: application/json' \\\n  -d '%s' \\\n  %s/campaigns",
					wp_json_encode(
						[
							'name'   => 'Black Friday 2026',
							'status' => 'draft',
						]
					),
					$base_url
				),
				'curl_activate_by_slug' => sprintf(
					"# 1. Look up the id by slug\ncurl -H 'Authorization: Bearer usc_live_xxx' %s/campaigns/by-slug/black-friday-2026\n\n# 2. Flip it live\ncurl -X POST -H 'Authorization: Bearer usc_live_xxx' %s/campaigns/{id}/activate",
					$base_url,
					$base_url
				),
				'curl_add_banner' => sprintf(
					"curl -X POST -H 'Authorization: Bearer usc_live_xxx' -H 'Content-Type: application/json' \\\n  -d '%s' \\\n  %s/campaigns/{id}/banners",
					wp_json_encode(
						[
							'device'      => 'desktop',
							'image_id'    => 1234,
							'link_url'    => 'https://example.com/promo',
							'alt_text'    => 'Black Friday — up to 70% off',
						]
					),
					$base_url
				),
			],
		];

		return new WP_REST_Response( $schema, 200 );
	}

	/* --------------------------- model schemas --------------------------- */

	private function describe_campaign(): array {
		return [
			'description' => 'A campaign groups banners under a slug and shared layout/behavior settings.',
			'fields'      => [
				'id'                => [ 'type' => 'integer', 'readonly' => true ],
				'name'              => [ 'type' => 'string', 'required' => true, 'example' => 'Black Friday 2026' ],
				'slug'              => [ 'type' => 'string', 'pattern' => 'a-z0-9-', 'note' => 'Auto-generated from name if omitted. Used in the shortcode.' ],
				'status'            => [ 'type' => 'enum', 'values' => [ 'draft', 'active', 'paused' ], 'default' => 'draft' ],
				'start_date'        => [ 'type' => 'datetime|null', 'format' => 'YYYY-MM-DD HH:MM:SS', 'note' => 'UTC. Campaign won\'t render before this.' ],
				'end_date'          => [ 'type' => 'datetime|null', 'format' => 'YYYY-MM-DD HH:MM:SS', 'note' => 'UTC. Campaign won\'t render after this.' ],
				'settings'          => [ 'type' => 'object', 'shape' => 'See models.settings.fields' ],
				'banners'           => [ 'type' => 'array<banner>', 'note' => 'Returned on detail/by-slug endpoints, omitted from list endpoint.' ],
				'shortcode_desktop' => [ 'type' => 'string', 'readonly' => true, 'example' => '[carouseldesktop_black-friday-2026]' ],
				'shortcode_mobile'  => [ 'type' => 'string', 'readonly' => true, 'example' => '[carouselmobile_black-friday-2026]' ],
				'created_at'        => [ 'type' => 'datetime', 'readonly' => true ],
				'updated_at'        => [ 'type' => 'datetime', 'readonly' => true ],
			],
			'partial_updates' => 'PUT supports partial updates — fields you omit are preserved. Sending {status:"active"} alone WILL NOT wipe the rest.',
		];
	}

	private function describe_group(): array {
		return [
			'description' => 'A named bucket of banners inside a carousel, scoped to a single device.',
			'fields'      => [
				'id'          => [ 'type' => 'integer', 'readonly' => true ],
				'campaign_id' => [ 'type' => 'integer', 'readonly' => true ],
				'device'      => [ 'type' => 'enum', 'values' => [ 'desktop', 'mobile' ], 'required' => true ],
				'name'        => [ 'type' => 'string', 'required' => true, 'example' => 'Black Friday' ],
				'is_active'   => [ 'type' => 'boolean', 'default' => true, 'note' => 'Flip this to false to hide every banner in the group without deleting them.' ],
				'sort_order'  => [ 'type' => 'integer', 'note' => 'Order among groups within the same (campaign, device).' ],
				'created_at'  => [ 'type' => 'datetime', 'readonly' => true ],
				'updated_at'  => [ 'type' => 'datetime', 'readonly' => true ],
			],
		];
	}

	private function describe_banner(): array {
		return [
			'description' => 'One image inside a group. The group it lives in (group_id) decides which carousel and which device tab it renders under.',
			'fields' => [
				'id'           => [ 'type' => 'integer', 'readonly' => true ],
				'campaign_id'  => [ 'type' => 'integer', 'readonly' => true ],
				'group_id'     => [ 'type' => 'integer', 'note' => 'When omitted on POST, the banner lands in the campaign+device\'s default group (one is created if needed).' ],
				'device'       => [ 'type' => 'enum', 'values' => [ 'desktop', 'mobile' ], 'required' => true ],
				'image_id'     => [ 'type' => 'integer', 'required' => true, 'note' => 'WP attachment ID. Upload via the Media Library REST API first.' ],
				'image'        => [ 'type' => 'object', 'readonly' => true, 'shape' => '{ id, url, width, height, srcset, sizes, alt }' ],
				'link_url'     => [ 'type' => 'url|null' ],
				'link_target'  => [ 'type' => 'enum', 'values' => [ '_self', '_blank' ], 'default' => '_self' ],
				'link_rel'     => [ 'type' => 'string|null', 'note' => 'Auto-set to "noopener noreferrer" when target is _blank, unless overridden.' ],
				'alt_text'     => [ 'type' => 'string|null', 'note' => 'Falls back to the attachment\'s alt text if empty.' ],
				'sort_order'   => [ 'type' => 'integer', 'note' => 'Order within the group. Auto-assigned to next position on append; PUT /banners/{bid} {sort_order:N} to move.' ],
				'is_active'    => [ 'type' => 'boolean', 'default' => true, 'note' => 'Per-banner pause toggle.' ],
				'created_at'   => [ 'type' => 'datetime', 'readonly' => true ],
			],
		];
	}

	private function describe_settings(): array {
		$defaults = Campaign_Repository::default_settings();
		return [
			'description' => 'Per-campaign layout and behavior knobs. All optional — defaults shown.',
			'fields'      => [
				'slides_per_view_desktop' => [ 'type' => 'enum', 'values' => [ 1, 1.5, 2, 2.5, 3, 3.5, 4, 4.5, 5 ], 'default' => $defaults['slides_per_view_desktop'] ],
				'slides_per_view_mobile'  => [ 'type' => 'enum', 'values' => [ 1, 1.5, 2, 2.5, 3, 3.5, 4, 4.5, 5 ], 'default' => $defaults['slides_per_view_mobile'] ],
				'gap'                     => [ 'type' => 'integer', 'min' => 0, 'max' => 80, 'unit' => 'px', 'default' => $defaults['gap'] ],
				'autoplay'                => [ 'type' => 'boolean', 'default' => $defaults['autoplay'] ],
				'autoplay_delay'          => [ 'type' => 'integer', 'min' => 1000, 'max' => 30000, 'unit' => 'ms', 'default' => $defaults['autoplay_delay'] ],
				'loop'                    => [ 'type' => 'boolean', 'default' => $defaults['loop'] ],
				'navigation'              => [ 'type' => 'enum', 'values' => [ 'none', 'dots', 'arrows' ], 'default' => $defaults['navigation'] ],
				'show_progress'           => [ 'type' => 'boolean', 'default' => $defaults['show_progress'] ],
				'pause_on_hover'          => [ 'type' => 'boolean', 'default' => $defaults['pause_on_hover'] ],
				'border_radius'           => [ 'type' => 'integer', 'min' => 0, 'max' => 64, 'unit' => 'px', 'default' => $defaults['border_radius'] ],
				'transition'              => [ 'type' => 'enum', 'values' => [ 'slide', 'fade' ], 'default' => $defaults['transition'] ],
				'aspect_ratio_desktop'    => [ 'type' => 'string', 'examples' => [ 'auto', '16/9', '21/9', '1560x1080', '16:9' ], 'default' => $defaults['aspect_ratio_desktop'], 'note' => '"auto" lets the image dictate the slide height.' ],
				'aspect_ratio_mobile'     => [ 'type' => 'string', 'examples' => [ 'auto', '4/5', '9/16', '1080x1920' ], 'default' => $defaults['aspect_ratio_mobile'] ],
			],
		];
	}

	private function describe_api_key(): array {
		return [
			'description' => 'Bearer-token credential for the API.',
			'fields'      => [
				'id'           => [ 'type' => 'integer', 'readonly' => true ],
				'name'         => [ 'type' => 'string', 'required' => true ],
				'scope'        => [ 'type' => 'enum', 'values' => [ 'read', 'write' ], 'default' => 'read' ],
				'masked'       => [ 'type' => 'string', 'readonly' => true ],
				'last_used_at' => [ 'type' => 'datetime|null', 'readonly' => true ],
				'last_used_ip' => [ 'type' => 'string|null', 'readonly' => true ],
				'is_active'    => [ 'type' => 'boolean', 'readonly' => true ],
				'created_at'   => [ 'type' => 'datetime', 'readonly' => true ],
			],
			'note' => 'The plain key value is returned ONLY in the create response and is not recoverable afterwards.',
		];
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function describe_endpoints( string $base ): array {
		return [
			[
				'method'   => 'GET',
				'path'     => '/discover',
				'auth'     => 'public',
				'summary'  => 'This document.',
			],

			// Campaigns — primary CRUD
			[
				'method'   => 'GET',
				'path'     => '/campaigns',
				'auth'     => 'read',
				'summary'  => 'List campaigns. Supports ?search and ?status filters.',
				'returns'  => 'array<campaign>',
			],
			[
				'method'   => 'POST',
				'path'     => '/campaigns',
				'auth'     => 'write',
				'summary'  => 'Create a campaign. Body: campaign fields (name required).',
				'returns'  => 'campaign',
			],
			[
				'method'   => 'GET',
				'path'     => '/campaigns/{id}',
				'auth'     => 'read',
				'summary'  => 'Get one campaign with its banners.',
				'returns'  => 'campaign',
			],
			[
				'method'   => 'PUT',
				'path'     => '/campaigns/{id}',
				'auth'     => 'write',
				'summary'  => 'Partial update — fields you omit are preserved.',
				'returns'  => 'campaign',
			],
			[
				'method'   => 'DELETE',
				'path'     => '/campaigns/{id}',
				'auth'     => 'write',
				'summary'  => 'Delete campaign and all its banners.',
			],

			// Verbose / AI-friendly
			[
				'method'   => 'GET',
				'path'     => '/campaigns/by-slug/{slug}',
				'auth'     => 'read',
				'summary'  => 'Get a campaign by its slug. Most integrations have a slug, not an id.',
				'returns'  => 'campaign',
			],
			[
				'method'   => 'PUT',
				'path'     => '/campaigns/by-slug/{slug}',
				'auth'     => 'write',
				'summary'  => 'Partial update by slug.',
				'returns'  => 'campaign',
			],
			[
				'method'   => 'DELETE',
				'path'     => '/campaigns/by-slug/{slug}',
				'auth'     => 'write',
				'summary'  => 'Delete by slug.',
			],
			[
				'method'   => 'POST',
				'path'     => '/campaigns/{id}/activate',
				'auth'     => 'write',
				'summary'  => 'Set status = active.',
				'returns'  => 'campaign',
			],
			[
				'method'   => 'POST',
				'path'     => '/campaigns/{id}/deactivate',
				'auth'     => 'write',
				'summary'  => 'Set status = paused.',
				'returns'  => 'campaign',
			],

			// Banners
			[
				'method'   => 'GET',
				'path'     => '/campaigns/{id}/banners',
				'auth'     => 'read',
				'summary'  => 'List banners for a campaign. Optional ?device=desktop|mobile filter.',
				'returns'  => 'array<banner>',
			],
			[
				'method'   => 'POST',
				'path'     => '/campaigns/{id}/banners',
				'auth'     => 'write',
				'summary'  => 'Append a single banner. Body: banner fields (image_id + device required).',
				'returns'  => 'campaign',
			],
			[
				'method'   => 'DELETE',
				'path'     => '/campaigns/{id}/banners/{bid}',
				'auth'     => 'write',
				'summary'  => 'Remove a single banner.',
				'returns'  => 'campaign',
			],

			// Groups
			[
				'method'   => 'GET',
				'path'     => '/campaigns/{id}/groups',
				'auth'     => 'read',
				'summary'  => 'List groups inside a campaign. Optional ?device=desktop|mobile filter.',
				'returns'  => 'array<group>',
			],
			[
				'method'   => 'POST',
				'path'     => '/campaigns/{id}/groups',
				'auth'     => 'write',
				'summary'  => 'Create a group. Body: { device, name }.',
				'returns'  => 'group',
			],
			[
				'method'   => 'GET',
				'path'     => '/groups/{gid}',
				'auth'     => 'read',
				'summary'  => 'Get a single group.',
				'returns'  => 'group',
			],
			[
				'method'   => 'PUT',
				'path'     => '/groups/{gid}',
				'auth'     => 'write',
				'summary'  => 'Partial update — name, is_active (pause/resume), sort_order.',
				'returns'  => 'group',
			],
			[
				'method'   => 'DELETE',
				'path'     => '/groups/{gid}',
				'auth'     => 'write',
				'summary'  => 'Delete the group AND all its banners (cascade).',
			],
			[
				'method'   => 'POST',
				'path'     => '/groups/{gid}/banners',
				'auth'     => 'write',
				'summary'  => 'Append one banner to a group. Body: { image_id, link_url?, alt_text?, link_target? }.',
				'returns'  => 'campaign',
			],

			// Per-banner ops
			[
				'method'   => 'PUT',
				'path'     => '/banners/{bid}',
				'auth'     => 'write',
				'summary'  => 'Partial update of a single banner — toggle is_active, link, target, alt, sort_order, group.',
			],
			[
				'method'   => 'DELETE',
				'path'     => '/banners/{bid}',
				'auth'     => 'write',
				'summary'  => 'Delete a single banner.',
			],

			// Settings
			[
				'method'   => 'GET',
				'path'     => '/settings',
				'auth'     => 'read',
				'summary'  => 'Get plugin-wide settings (e.g. language).',
			],
			[
				'method'   => 'PUT',
				'path'     => '/settings',
				'auth'     => 'write',
				'summary'  => 'Partial update of plugin-wide settings.',
			],

			// Aliases
			[
				'method'   => '*',
				'path'     => '/carousels/*',
				'auth'     => 'inherit',
				'summary'  => 'Alias for /campaigns/*. The product calls these "carousels"; the data layer kept "campaign" for backwards compatibility. Both work, identical responses.',
			],
		];
	}
}
