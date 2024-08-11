<?php

namespace App\Filament\App\Resources\FileResource\Pages;

use AbdelhamidErrahmouni\FilamentMonacoEditor\MonacoEditor;
use App\Facades\Activity;
use App\Filament\App\Resources\FileResource;
use App\Models\File;
use App\Models\Permission;
use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Concerns\HasUnsavedDataChangesAlert;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Panel;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\PageRegistration;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Livewire\Attributes\Locked;

/**
 * @property Form $form
 */
class EditFiles extends Page
{
    use HasUnsavedDataChangesAlert;
    use InteractsWithFormActions;
    use InteractsWithForms;

    protected static string $resource = FileResource::class;

    protected static string $view = 'filament.app.pages.edit-file';

    #[Locked]
    public string $path;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        File::get($server, dirname($this->path))->orderByDesc('is_directory')->orderBy('name');

        return $form
            ->schema([
                Select::make('lang')
                    ->label('')
                    ->placeholder('File Language')
                    ->inlineLabel()
                    ->live()
                    ->default(function () {
                        $split = explode('.', $this->path);
                        return end($split);
                    })
                    ->options([
                        'Plain Text' => 'plaintext',
                        'abap',
                        'apex',
                        'azcali',
                        'bat',
                        'bicep',
                        'cameligo',
                        'coljure',
                        'coffeescript',
                        'c',
                        'cpp',
                        'csharp',
                        'csp',
                        'css',
                        'cypher',
                        'dart',
                        'dockerfile',
                        'ecl',
                        'elixir',
                        'flow9',
                        'fsharp',
                        'go',
                        'graphql',
                        'handlebars',
                        'hcl',
                        'html',
                        'ini',
                        'java',
                        'javascript',
                        'julia',
                        'kotlin',
                        'less',
                        'lexon',
                        'lua',
                        'liquid',
                        'm3',
                        'markdown',
                        'mdx',
                        'mips',
                        'msdax',
                        'mysql',
                        'objective-c',
                        'pascal',
                        'pascaligo',
                        'perl',
                        'pgsql',
                        'php',
                        'pla',
                        'postiats',
                        'powerquery',
                        'powershell',
                        'proto',
                        'pug',
                        'python',
                        'qsharp',
                        'r',
                        'razor',
                        'redis',
                        'redshift',
                        'restructuredtext',
                        'ruby',
                        'rust',
                        'sb',
                        'scala',
                        'scheme',
                        'scss',
                        'shell',
                        'sol',
                        'aes',
                        'sparql',
                        'sql',
                        'st',
                        'swift',
                        'systemverilog',
                        'verilog',
                        'tcl',
                        'twig',
                        'typescript',
                        'typespec',
                        'vb',
                        'wgsl',
                        'xml',
                        'yaml',
                        'json',
                    ]),
                MonacoEditor::make('editor')
                    ->columnSpanFull()
                    ->label('')
                    ->language(fn (Get $get) => $get('lang') ?? 'plaintext')
                    ->view('filament.plugins.monaco-editor')
                    ->formatStateUsing(function () {
                        /** @var Server $server */
                        $server = Filament::getTenant();

                        return app(DaemonFileRepository::class)
                            ->setServer($server)
                            ->getContent($this->path, config('panel.files.max_edit_size'));
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Changes')
                ->icon('tabler-device-floppy')
                ->keyBindings('mod+s')
                ->action(function () {
                    /** @var Server $server */
                    $server = Filament::getTenant();

                    $data = $this->form->getState();

                    app(DaemonFileRepository::class)
                        ->setServer($server)
                        ->putContent($this->path, $data['editor'] ?? '');

                    Activity::event('server:file.write')
                        ->property('file', $this->path)
                        ->log();
                }),
            Action::make('cancel')
                ->label('Cancel')
                ->color('danger')
                ->icon('tabler-x')
                ->url(fn () => ListFiles::getUrl(['path' => dirname($this->path)])),
        ];
    }

    public function mount(string $path): void
    {
        $this->authorizeAccess();

        $this->path = $path;

        $this->form->fill();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(!auth()->user()->can(Permission::ACTION_FILE_UPDATE), 403);
    }

    /**
     * @return array<int | string, string | Form>
     */
    protected function getForms(): array
    {
        return [
            'form' => $this->form(static::getResource()::form(
                $this->makeForm()
                    ->statePath($this->getFormStatePath())
                    ->columns($this->hasInlineLabels() ? 1 : 2)
                    ->inlineLabel($this->hasInlineLabels()),
            )),
        ];
    }

    public function getFormStatePath(): ?string
    {
        return 'data';
    }

    public function getBreadcrumbs(): array
    {
        $resource = static::getResource();

        $breadcrumbs = [
            $resource::getUrl() => $resource::getBreadcrumb(),
        ];

        $previousParts = '';
        foreach (explode('/', $this->path) as $part) {
            $previousParts = $previousParts . '/' . $part;
            $breadcrumbs[self::getUrl(['path' => ltrim($previousParts, '/')])] = $part;
        }

        return $breadcrumbs;
    }

    public static function route(string $path): PageRegistration
    {
        return new PageRegistration(
            page: static::class,
            route: fn (Panel $panel): Route => RouteFacade::get($path, static::class)
                ->middleware(static::getRouteMiddleware($panel))
                ->withoutMiddleware(static::getWithoutRouteMiddleware($panel))
                ->where('path', '.*'),
        );
    }
}
