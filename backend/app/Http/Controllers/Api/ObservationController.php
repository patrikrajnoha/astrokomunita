<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Observation\StoreObservationRequest;
use App\Http\Requests\Observation\UpdateObservationRequest;
use App\Models\Observation;
use App\Models\User;
use App\Services\ObservationPayloadService;
use App\Services\ObservationService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ObservationController extends Controller
{
    public function __construct(
        private readonly ObservationService $observations,
        private readonly ObservationPayloadService $payloads,
    ) {
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest'])],
        ]);

        $viewer = $this->resolveViewer($request);
        $mine = $request->boolean('mine');

        if ($mine && !$viewer) {
            return response()->json([
                'message' => 'Neprihlaseny pouzivatel.',
            ], 401);
        }

        $perPage = (int) ($validated['per_page'] ?? $request->query('per_page', 20));
        $perPage = max(1, min($perPage, 50));
        $sort = (string) ($validated['sort'] ?? 'newest');

        $query = Observation::query()
            ->with([
                'user:id,name,username,location,bio,is_admin,avatar_path,cover_path',
                'event:id,title,type,start_at,end_at,max_at,short',
                'media',
            ]);

        if ($sort === 'oldest') {
            $query
                ->orderByRaw('observed_at IS NULL')
                ->orderBy('observed_at')
                ->orderBy('id');
        } else {
            $query
                ->orderByRaw('observed_at IS NULL')
                ->orderByDesc('observed_at')
                ->orderByDesc('id');
        }

        if ($request->filled('event_id')) {
            $query->where('event_id', (int) $request->query('event_id'));
        }

        if (!$mine && $request->filled('user_id')) {
            $userId = (int) $request->query('user_id');
            if ($userId > 0) {
                $query->where('user_id', $userId);
            }
        }

        if ($mine && $viewer) {
            $query->where('user_id', $viewer->id);
        } elseif ($viewer) {
            $query->where(function ($visibilityQuery) use ($viewer): void {
                $visibilityQuery
                    ->where('is_public', true)
                    ->orWhere('user_id', $viewer->id);
            });
        } else {
            $query->where('is_public', true);
        }

        $paginator = $query->paginate($perPage)->withQueryString();

        return response()->json(
            $this->payloads->serializePaginator($paginator, $viewer)
        );
    }

    public function show(Request $request, Observation $observation)
    {
        $viewer = $this->resolveViewer($request);
        $canView = $viewer
            ? Gate::forUser($viewer)->allows('view', $observation)
            : Gate::allows('view', $observation);

        if (!$canView) {
            return response()->json([
                'message' => 'Not found.',
            ], 404);
        }

        return response()->json(
            $this->payloads->serializeObservation($observation, $viewer)
        );
    }

    public function store(StoreObservationRequest $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Neprihlaseny pouzivatel.',
            ], 401);
        }

        if (Gate::forUser($user)->denies('create', Observation::class)) {
            return response()->json([
                'message' => 'Nemate opravnenie vytvorit pozorovanie.',
            ], 403);
        }

        $images = $this->normalizeImages($request->file('images', []));

        $observation = $this->observations->createObservation(
            $user,
            $request->validated(),
            $images
        );

        return response()->json(
            $this->payloads->serializeObservation($observation, $user),
            201
        );
    }

    public function update(UpdateObservationRequest $request, Observation $observation)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Neprihlaseny pouzivatel.',
            ], 401);
        }

        if (Gate::forUser($user)->denies('update', $observation)) {
            return response()->json([
                'message' => 'Nemate opravnenie upravit toto pozorovanie.',
            ], 403);
        }

        $images = $this->normalizeImages($request->file('images', []));
        $removeMediaIds = $request->validated('remove_media_ids') ?? [];

        $updated = $this->observations->updateObservation(
            $observation,
            $request->validated(),
            $images,
            is_array($removeMediaIds) ? $removeMediaIds : []
        );

        return response()->json(
            $this->payloads->serializeObservation($updated, $user)
        );
    }

    public function destroy(Request $request, Observation $observation)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Neprihlaseny pouzivatel.',
            ], 401);
        }

        if (Gate::forUser($user)->denies('delete', $observation)) {
            return response()->json([
                'message' => 'Nemate opravnenie zmazat toto pozorovanie.',
            ], 403);
        }

        $this->observations->deleteObservation($observation);

        return response()->noContent();
    }

    private function resolveViewer(Request $request): ?User
    {
        return $request->user() ?? $request->user('sanctum');
    }

    /**
     * @param array<int, UploadedFile>|UploadedFile|null $raw
     * @return array<int, UploadedFile>
     */
    private function normalizeImages(array|UploadedFile|null $raw): array
    {
        if ($raw instanceof UploadedFile) {
            return [$raw];
        }

        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_filter($raw, static fn (mixed $file): bool => $file instanceof UploadedFile));
    }
}
