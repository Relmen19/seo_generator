<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer;

use Seo\Database;

class BlockRegistry
{
    /** @var array<string, string> type => class name */
    private array $map;

    /** @var array<string, BlockRendererInterface> type => instance cache */
    private array $instances = [];

    private Database $db;

    private ?ImageCache $imageCache = null;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->map = [
            'hero'              => Block\HeroBlockRenderer::class,
            'stats_counter'     => Block\StatsBlockRenderer::class,
            'richtext'          => Block\RichtextBlockRenderer::class,
            'range_table'       => Block\NormsTableBlockRenderer::class,
            'norms_table'       => Block\NormsTableBlockRenderer::class,
            'accordion'         => Block\AccordionBlockRenderer::class,
            'chart'             => Block\ChartBlockRenderer::class,
            'comparison_table'  => Block\ComparisonTableBlockRenderer::class,
            'image_section'     => Block\ImageSectionBlockRenderer::class,
            'faq'               => Block\FaqBlockRenderer::class,
            'cta'               => Block\CtaBlockRenderer::class,
            'feature_grid'      => Block\FeatureGridBlockRenderer::class,
            'testimonial'       => Block\TestimonialBlockRenderer::class,
            'gauge_chart'       => Block\GaugeChartBlockRenderer::class,
            'timeline'          => Block\TimelineBlockRenderer::class,
            'heatmap'           => Block\HeatmapBlockRenderer::class,
            'funnel'            => Block\FunnelBlockRenderer::class,
            'spark_metrics'     => Block\SparkMetricsBlockRenderer::class,
            'radar_chart'       => Block\RadarChartBlockRenderer::class,
            'before_after'      => Block\BeforeAfterBlockRenderer::class,
            'stacked_area'      => Block\StackedAreaBlockRenderer::class,
            'score_rings'       => Block\ScoreRingsBlockRenderer::class,
            'range_comparison'  => Block\RangeComparisonBlockRenderer::class,
            'value_checker'     => Block\ValueCheckerBlockRenderer::class,
            'criteria_checklist'=> Block\SymptomChecklistBlockRenderer::class,
            'symptom_checklist' => Block\SymptomChecklistBlockRenderer::class,
            'prep_checklist'    => Block\PrepChecklistBlockRenderer::class,
            'info_cards'        => Block\InfoCardsBlockRenderer::class,
            'story_block'       => Block\StoryBlockRenderer::class,
            'verdict_card'      => Block\VerdictCardBlockRenderer::class,
            'numbered_steps'    => Block\NumberedStepsBlockRenderer::class,
            'warning_block'     => Block\WarningBlockRenderer::class,
            'mini_calculator'   => Block\MiniCalculatorBlockRenderer::class,
            'comparison_cards'  => Block\ComparisonCardsBlockRenderer::class,
            'progress_tracker'  => Block\ProgressTrackerBlockRenderer::class,
            'key_takeaways'     => Block\KeyTakeawaysBlockRenderer::class,
            'expert_panel'      => Block\ExpertPanelBlockRenderer::class,
        ];
    }

    public function get(string $type): ?BlockRendererInterface
    {
        if (!isset($this->map[$type])) {
            return null;
        }

        if (!isset($this->instances[$type])) {
            $class = $this->map[$type];
            $instance = new $class($this->db);
            if ($this->imageCache !== null && $instance instanceof AbstractBlockRenderer) {
                $instance->setImageCache($this->imageCache);
            }
            $this->instances[$type] = $instance;
        }

        return $this->instances[$type];
    }

    public function setImageCache(ImageCache $cache): void
    {
        $this->imageCache = $cache;
        foreach ($this->instances as $instance) {
            if ($instance instanceof AbstractBlockRenderer) {
                $instance->setImageCache($cache);
            }
        }
    }

    public function has(string $type): bool
    {
        return isset($this->map[$type]);
    }

    /**
     * Register a custom block renderer for a type.
     */
    public function register(string $type, string $className): void
    {
        $this->map[$type] = $className;
        unset($this->instances[$type]);
    }
}
