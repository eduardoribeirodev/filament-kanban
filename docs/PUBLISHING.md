# Publishing and the Filament plugin directory

## Package identity

| Field | Value |
| --- | --- |
| Composer package | `eduardoribeirodev/filament-kanban` |
| Repository | https://github.com/eduardoribeirodev/filament-kanban |
| License | MIT |
| PHP | 8.4+ |
| Filament | 5.x |
| Livewire | 4.1+ |
| Author | Eduardo Ribeiro |

## Releases

1. Run `composer validate --strict`, `composer test`, and `npm ci && npm run build`.
2. Commit the source, compiled `dist/filament-kanban.css`, documentation, and changelog.
3. Create a semantic version tag and a GitHub release. Keep `version` out of `composer.json`; Composer reads Git tags.
4. After the first public push, [submit the public repository URL to Packagist](https://packagist.org/packages/submit) using the account that owns the `eduardoribeirodev` vendor namespace.
5. Confirm the tagged version appears on Packagist and enable GitHub synchronization for subsequent releases.

See [Packagist's publishing instructions](https://packagist.org/about#how-to-submit-packages).

## Dedicated page on filamentphp.com

The Filament team moved plugin submissions to the [author dashboard](https://filamentphp.com/author). The [old website repository](https://github.com/filamentphp/legacy-site) is archived and explicitly directs authors to this dashboard instead of pull requests.

Sign in and request access to the existing **Eduardo Ribeiro** author profile, if it is not linked to your account yet. The existing [Leaflet plugin page](https://filamentphp.com/plugins/eduardo-ribeiro-leaflet) belongs to this author. Once access is approved, create the Kanban listing through the dashboard and follow its current field and review requirements.

Suggested listing content:

| Field | Suggested content |
| --- | --- |
| Name | Kanban |
| Short description | Build Kanban boards with native Filament filters, actions, card layouts, drag and drop, and progressive loading. |
| Author | Eduardo Ribeiro |
| Repository | https://github.com/eduardoribeirodev/filament-kanban |
| Composer package | `eduardoribeirodev/filament-kanban` |
| Documentation | https://github.com/eduardoribeirodev/filament-kanban#readme |
| Raw documentation, if requested | https://raw.githubusercontent.com/eduardoribeirodev/filament-kanban/main/README.md |
| Supported Filament version | 5.x |
| Theme support | Light and dark |
| Languages | English and Brazilian Portuguese |
| Price / license | Free / MIT |

The screenshots in [`docs/images`](images) use fictional data and Filament's standard theme. The [demo instructions](demo/README.md) explain how to reproduce them. Check the dashboard for its current image dimensions and upload requirements before submitting.

A directory listing presents this package as a community plugin with its own page. Incorporating a Kanban component into Filament's core would be a separate feature proposal and code contribution, requiring agreement from Filament's maintainers.
