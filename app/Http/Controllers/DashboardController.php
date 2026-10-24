<?php
namespace App\Http\Controllers;

use App\Domain\Trade\WflCalculator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(WflCalculator $calculator): Response
    {
        $wfl = $calculator->calculate(
            [['id' => 'abyssal-current', 'quantity' => 1, 'unit_value' => 2400]],
            [['id' => 'aurora-rod', 'quantity' => 1, 'unit_value' => 2600]], 10, 'fixture-2026.10.24', 'viewer'
        );
        return Inertia::render('discovery', [
            'synthetic' => true,
            'snapshot' => ['version' => 'fixture-2026.10.24', 'ageLabel' => 'Synthetic fixture · 24 Oct 2026'],
            'wfl' => $wfl->toArray(),
            'listings' => [
                ['id' => 'lst_demo_01', 'alias' => 'Angler 28', 'offered' => 'Abyssal Current', 'wanted' => 'Aurora Rod', 'value' => 2400, 'age' => '4m', 'status' => 'fair'],
                ['id' => 'lst_demo_02', 'alias' => 'Angler 07', 'offered' => 'Tidebreaker', 'wanted' => 'Celestial Skin', 'value' => 4100, 'age' => '11m', 'status' => 'win'],
                ['id' => 'lst_demo_03', 'alias' => 'Angler 91', 'offered' => 'Aurora Rod', 'wanted' => 'Abyssal Current + add', 'value' => 3200, 'age' => '18m', 'status' => 'fair'],
                ['id' => 'lst_demo_04', 'alias' => 'Angler 44', 'offered' => 'Sunken Crown', 'wanted' => 'Tidebreaker', 'value' => 3800, 'age' => '24m', 'status' => 'lose'],
            ],
            'filterOptions' => [
                'items' => DB::table('catalog_items')->where('is_active', true)->orderBy('canonical_name')->get(['public_id as id', 'canonical_name as name']),
                'categories' => DB::table('catalog_categories')->where('is_active', true)->orderBy('name')->get(['public_id as id', 'name']),
            ],
        ]);
    }
}
