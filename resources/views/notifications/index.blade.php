@extends('layouts.app')

@section('page-title', 'Notificaciones')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="h3 mb-1">Notificaciones</h2>
            <p class="text-body-secondary mb-0">Consulta los avisos operativos relacionados con los vales de Enfermería.</p>
        </div>
        @if ($pending->total() > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button class="btn btn-outline-primary" type="submit">Marcar todas como leídas</button>
            </form>
        @endif
    </div>

    @foreach ([['title' => 'Pendientes', 'items' => $pending, 'unread' => true], ['title' => 'Anteriores', 'items' => $previous, 'unread' => false]] as $section)
        <section class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3"><h3 class="h5 mb-0">{{ $section['title'] }}</h3></div>
            <div class="list-group list-group-flush">
                @forelse ($section['items'] as $notification)
                    @php
                        $data = $notification->data;
                        $actionUrl = $actionLinks->get($notification->id);
                        $status = isset($data['status']) ? \App\Enums\NursingVoucherStatus::tryFrom($data['status']) : null;
                        $source = isset($data['source_type']) ? \App\Enums\NursingSupplySourceType::tryFrom($data['source_type']) : null;
                    @endphp
                    <article class="list-group-item p-3 {{ $section['unread'] ? 'bg-primary-subtle' : '' }}">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                            <div>
                                <p class="fw-semibold mb-2">{{ $data['message'] ?? 'Notificación operativa.' }}</p>
                                <div class="d-flex flex-wrap gap-2 small text-body-secondary">
                                    <span><i class="bi bi-person me-1"></i>{{ $data['patient_name'] ?? '—' }}</span>
                                    <span><i class="bi bi-door-open me-1"></i>Habitación {{ $data['room_number'] ?? '—' }}</span>
                                    @if ($status)<span class="badge text-bg-secondary">{{ $status->label() }}</span>@endif
                                    @if ($source)<span>{{ $source->label() }}</span>@endif
                                </div>
                                <small class="text-body-secondary d-block mt-2">{{ $notification->created_at->format('d/m/Y H:i') }}</small>
                            </div>
                            <div class="d-flex flex-wrap align-items-start gap-2">
                                @if ($actionUrl)
                                    <a class="btn btn-sm btn-primary" href="{{ $actionUrl }}">Ver vale</a>
                                @endif
                                @if ($section['unread'])
                                    <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">Marcar como leída</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="list-group-item py-4 text-center text-body-secondary">No hay notificaciones en esta sección.</div>
                @endforelse
            </div>
            @if ($section['items']->hasPages())
                <div class="card-footer bg-white">{{ $section['items']->withQueryString()->links() }}</div>
            @endif
        </section>
    @endforeach
@endsection
