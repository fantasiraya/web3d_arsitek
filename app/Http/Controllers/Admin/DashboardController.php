<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Models\Plan;
use App\Domains\Project\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->query('filter', '30days');
        $startDate = null;
        $endDate = Carbon::now()->endOfDay();

        switch ($filter) {
            case 'today':
                $startDate = Carbon::today()->startOfDay();
                break;
            case '7days':
                $startDate = Carbon::now()->subDays(6)->startOfDay();
                break;
            case '30days':
                $startDate = Carbon::now()->subDays(29)->startOfDay();
                break;
            case 'this_month':
                $startDate = Carbon::now()->startOfMonth();
                break;
            case 'last_month':
                $startDate = Carbon::now()->subMonth()->startOfMonth();
                $endDate = Carbon::now()->subMonth()->endOfMonth();
                break;
            case 'custom':
                if ($request->query('from')) {
                    $startDate = Carbon::parse($request->query('from'))->startOfDay();
                }
                if ($request->query('to')) {
                    $endDate = Carbon::parse($request->query('to'))->endOfDay();
                }
                $startDate = $startDate ?? Carbon::now()->subDays(29)->startOfDay();
                break;
            default:
                $startDate = Carbon::now()->subDays(29)->startOfDay();
                $filter = '30days';
                break;
        }

        // STATISTIC CARDS
        $totalUsers = User::count();
        $activeUsers = User::where('status', '!=', 'suspended')->count();
        $freeUsers = User::where('subscription_status', 'free')->count();
        $proUsers = User::where('subscription_status', 'pro')->count();
        $enterpriseUsers = User::where('subscription_status', 'enterprise')->count();
        $totalProjects = Project::count();

        $startOfThisMonth = Carbon::now()->startOfMonth();
        $projectsCreatedThisMonth = Project::where('created_at', '>=', $startOfThisMonth)->count();
        $newUsersThisMonth = User::where('created_at', '>=', $startOfThisMonth)->count();

        // CHARTS DATA
        // 1. User & Project Growth timeline over selected period
        $daysDiff = max(1, (int) $startDate->diffInDays($endDate));
        $growthLabels = [];
        $usersGrowthData = [];
        $projectsGrowthData = [];

        // If range <= 35 days, group by day. Otherwise group by week or month.
        if ($daysDiff <= 35) {
            $current = $startDate->copy();
            while ($current <= $endDate) {
                $dateStr = $current->format('Y-m-d');
                $label = $current->format('d M');
                $growthLabels[] = $label;

                $usersGrowthData[] = User::whereDate('created_at', $dateStr)->count();
                $projectsGrowthData[] = Project::whereDate('created_at', $dateStr)->count();

                $current->addDay();
            }
        } else {
            // Group by month
            $current = $startDate->copy()->startOfMonth();
            while ($current <= $endDate) {
                $monthStr = $current->format('Y-m');
                $label = $current->format('M Y');
                $growthLabels[] = $label;

                $usersGrowthData[] = User::whereYear('created_at', $current->year)
                    ->whereMonth('created_at', $current->month)
                    ->count();

                $projectsGrowthData[] = Project::whereYear('created_at', $current->year)
                    ->whereMonth('created_at', $current->month)
                    ->count();

                $current->addMonth();
            }
        }

        // 2. Subscription Distribution
        $subscriptionDistribution = [
            'free' => $freeUsers,
            'pro' => $proUsers,
            'enterprise' => $enterpriseUsers,
        ];

        // 3. Project Distribution per Plan
        $projectDistributionPerPlan = [
            'free' => Project::whereHas('user', fn ($q) => $q->where('subscription_status', 'free'))->count(),
            'pro' => Project::whereHas('user', fn ($q) => $q->where('subscription_status', 'pro'))->count(),
            'enterprise' => Project::whereHas('user', fn ($q) => $q->where('subscription_status', 'enterprise'))->count(),
        ];

        // 4. User Activity stats
        $activeLast7Days = User::where('last_active_at', '>=', Carbon::now()->subDays(7))->count();
        $suspendedUsers = User::where('status', 'suspended')->count();

        $userActivity = [
            'active_recent' => $activeLast7Days,
            'active_total' => $activeUsers,
            'suspended' => $suspendedUsers,
        ];

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'free_users' => $freeUsers,
                'pro_users' => $proUsers,
                'enterprise_users' => $enterpriseUsers,
                'total_projects' => $totalProjects,
                'projects_created_this_month' => $projectsCreatedThisMonth,
                'new_users_this_month' => $newUsersThisMonth,
            ],
            'charts' => [
                'labels' => $growthLabels,
                'user_growth' => $usersGrowthData,
                'project_growth' => $projectsGrowthData,
                'subscription_distribution' => $subscriptionDistribution,
                'project_distribution' => $projectDistributionPerPlan,
                'user_activity' => $userActivity,
            ],
            'filters' => [
                'filter' => $filter,
                'from' => $request->query('from', $startDate->format('Y-m-d')),
                'to' => $request->query('to', $endDate->format('Y-m-d')),
            ],
        ]);
    }
}
