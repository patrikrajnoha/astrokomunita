<?php

namespace Tests\Unit\EventImport;

use App\Services\EventImport\EventTypeClassifier;
use Tests\TestCase;

class EventTypeClassifierTest extends TestCase
{
    public function test_it_classifies_moon_phase_raw_type_as_observation_window(): void
    {
        $classifier = app(EventTypeClassifier::class);

        $type = $classifier->classify('moon_phase', 'Last Quarter Moon');

        $this->assertSame('observation_window', $type);
    }

    public function test_it_classifies_moon_phase_title_as_observation_window(): void
    {
        $classifier = app(EventTypeClassifier::class);

        $type = $classifier->classify(null, 'First Quarter Moon');

        $this->assertSame('observation_window', $type);
    }

    public function test_it_keeps_meteor_shower_classification_unchanged(): void
    {
        $classifier = app(EventTypeClassifier::class);

        $type = $classifier->classify('meteor_shower', 'Lyrids Meteor Shower');

        $this->assertSame('meteor_shower', $type);
    }

    public function test_it_normalizes_single_meteor_events_to_supported_taxonomy(): void
    {
        $classifier = app(EventTypeClassifier::class);

        $type = $classifier->classify('fireball', 'Bright fireball over Europe');

        $this->assertSame('meteors', $type);
    }
}
