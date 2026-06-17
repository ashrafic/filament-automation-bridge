<?php

namespace Ashrafic\FilamentAutomationBridge\Filament\Components;

use Ashrafic\FilamentAutomationBridge\Services\FieldSchemaAnalyzer;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;

class FieldMappingBuilder extends Component
{
    protected string|Closure|null $modelClass = null;

    public function modelClass(string|Closure|null $modelClass): static
    {
        $this->modelClass = $modelClass;

        return $this;
    }

    public function getModelClass(): ?string
    {
        return $this->evaluate($this->modelClass);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema([
            CheckboxList::make('field_mapping')
                ->label('Fields')
                ->columns(2)
                ->searchable()
                ->bulkToggleable()
                ->options(function (Get $get, FieldMappingBuilder $component) {
                    $modelClass = $component->getModelClass();

                    if (! $modelClass || ! class_exists($modelClass)) {
                        return [];
                    }

                    $analyzer = app(FieldSchemaAnalyzer::class);
                    $schema = $analyzer->analyze($modelClass);

                    return $component->buildFlatOptions($schema);
                })
                ->visible(fn (Get $get, FieldMappingBuilder $component) => filled($component->getModelClass()))
                ->columnSpanFull(),

            Placeholder::make('no_model_selected')
                ->label('No Model Selected')
                ->content('Select a model first to configure field mapping.')
                ->visible(fn (Get $get, FieldMappingBuilder $component) => blank($component->getModelClass())),
        ]);
    }

    public function buildFlatOptions(array $schema, string $prefix = ''): array
    {
        $options = [];

        foreach ($schema['attributes'] ?? [] as $attr) {
            $name = is_array($attr) ? $attr['name'] : $attr;
            $key = $prefix ? "{$prefix}.{$name}" : $name;
            $options[$key] = $key;
        }

        foreach ($schema['relations'] ?? [] as $relation) {
            $relationName = $relation['name'];
            $relationType = $relation['type'] ?? '';
            $fullPrefix = $prefix ? "{$prefix}.{$relationName}" : $relationName;

            if (in_array($relationType, ['HasMany', 'MorphMany'])) {
                $relatedAttrs = $relation['attributes'] ?? [];

                $wildcardKey = "{$fullPrefix}.*";
                $options[$wildcardKey] = "{$fullPrefix}.* (all fields)";

                foreach ($relatedAttrs as $attr) {
                    $attrName = is_array($attr) ? $attr['name'] : $attr;
                    $fieldKey = "{$fullPrefix}.*.{$attrName}";
                    $options[$fieldKey] = $fieldKey;
                }
            } else {
                $relatedAttrs = $relation['attributes'] ?? [];

                if (empty($relatedAttrs) || $relation['model'] === 'polymorphic') {
                    $options[$fullPrefix] = $fullPrefix;

                    continue;
                }

                $nested = $this->buildFlatOptions($relation, $fullPrefix);
                $options = array_merge($options, $nested);
            }
        }

        return $options;
    }
}
