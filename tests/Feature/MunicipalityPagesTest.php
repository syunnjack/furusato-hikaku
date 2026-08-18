<?php

namespace Tests\Feature;

use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MunicipalityPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_municipality_pages_show_published_figures(): void
    {
        $city = Municipality::create($this->cityAttributes());

        $this->get('/jichitai')
            ->assertOk()
            ->assertSee('都城市')
            ->assertSee('総務省', false);

        $this->get('/jichitai/miyazaki')
            ->assertOk()
            ->assertSee('都城市')
            ->assertSee('21,646,358,900円');

        $this->get('/jichitai/miyazaki/'.$city->code)
            ->assertOk()
            ->assertSee('都城市のふるさと納税')
            ->assertSee('21,646,358,900')
            ->assertSee('子ども・子育て')
            ->assertSee('令和6年度');
    }

    public function test_unknown_prefecture_and_code_return_not_found(): void
    {
        Municipality::create($this->cityAttributes());

        $this->get('/jichitai/atlantis')->assertNotFound();
        $this->get('/jichitai/miyazaki/999999')->assertNotFound();
    }

    public function test_code_under_another_prefecture_redirects_to_the_right_url(): void
    {
        $city = Municipality::create($this->cityAttributes());

        $this->get('/jichitai/hokkaido/'.$city->code)
            ->assertRedirect('/jichitai/miyazaki/'.$city->code);
    }

    /** 総務省の現況調査から取り込む項目にそろえた1件分のデータ。 */
    private function cityAttributes(): array
    {
        return [
            'code' => '452025',
            'prefecture' => '宮崎県',
            'city' => '都城市',
            'amount' => 21646358900,
            'count' => 1058404,
            'outside_amount' => 21643138900,
            'outside_count' => 1058394,
            'cost_total' => 10724995009,
            'cost_ratio' => 49.5,
            'reward_provided' => true,
            'use_selectable' => true,
            'use_by_project' => false,
            'use_by_field' => true,
            'cf_projects' => 0,
            'cf_amount' => 0,
            'projects' => [],
            'field_breakdown' => [
                ['field' => '子ども・子育て', 'count' => 531220, 'amount' => 10827560000],
            ],
            'series' => [
                ['year' => '令和6年度', 'amount' => 17692073537, 'count' => 862624],
                ['year' => '令和7年度', 'amount' => 21646358900, 'count' => 1058404],
            ],
            'publish_amount' => true,
            'publish_usage' => true,
            'publish_progress' => true,
            'donor_relation' => 'はがき等で寄附の使い道を報告している。',
            'onestop_online' => '対応済み',
            'national_rank' => 3,
            'prefecture_rank' => 1,
        ];
    }
}
