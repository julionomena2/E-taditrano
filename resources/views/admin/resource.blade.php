@extends('layouts.admin')

@section('title', $title)
@section('page-title', $title)

@if ($model === 'etablissements')
    @push('scripts')
        <script>
            (() => {
                const mapElement = document.getElementById('establishment-map');
                const statusElement = document.getElementById('establishment-map-status');
                const latitudeInput = document.getElementById('latitude');
                const longitudeInput = document.getElementById('longitude');
                const quartierSelect = document.getElementById('id_quartier');
                const quartierLocations = @js($mapLocations);
                const dakar = { lat: 14.6928, lng: -17.4467 };
                const initialLatitude = Number(latitudeInput.value);
                const initialLongitude = Number(longitudeInput.value);
                const hasInitialPosition =
                    latitudeInput.value !== '' &&
                    longitudeInput.value !== '' &&
                    Number.isFinite(initialLatitude) &&
                    Number.isFinite(initialLongitude) &&
                    initialLatitude >= -90 &&
                    initialLatitude <= 90 &&
                    initialLongitude >= -180 &&
                    initialLongitude <= 180;
                const initialPosition = hasInitialPosition
                    ? { lat: initialLatitude, lng: initialLongitude }
                    : dakar;
                const urlNominatim = 'https://nominatim.openstreetmap.org';
                let map;
                let marker;
                let geocodingRequest = 0;
                let geocodingController;
                let lastNominatimRequestAt = 0;

                const normalizeName = (name) => name
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toLocaleLowerCase('fr')
                    .replace(/[^a-z0-9]+/g, ' ')
                    .trim();

                const waitForNominatimSlot = async (signal) => {
                    const delay = Math.max(0, 1000 - (Date.now() - lastNominatimRequestAt));
                    if (delay > 0) {
                        await new Promise((resolve, reject) => {
                            const timeout = window.setTimeout(resolve, delay);
                            signal.addEventListener('abort', () => {
                                window.clearTimeout(timeout);
                                reject(new DOMException('Requête annulée.', 'AbortError'));
                            }, { once: true });
                        });
                    }

                    if (signal.aborted) {
                        throw new DOMException('Requête annulée.', 'AbortError');
                    }

                    lastNominatimRequestAt = Date.now();
                };

                const requestNominatim = async (path, signal) => {
                    await waitForNominatimSlot(signal);
                    const response = await fetch(`${urlNominatim}${path}`, {
                        signal,
                        headers: { 'Accept-Language': 'fr' },
                    });

                    if (!response.ok) {
                        throw new Error(`Nominatim a répondu avec le statut ${response.status}.`);
                    }

                    return response.json();
                };

                const setCoordinateInputs = (latitude, longitude) => {
                    latitudeInput.value = Number(latitude).toFixed(7);
                    longitudeInput.value = Number(longitude).toFixed(7);
                };

                const setMarkerFromInputs = () => {
                    const lat = Number(latitudeInput.value);
                    const lng = Number(longitudeInput.value);

                    if (
                        latitudeInput.value === '' ||
                        longitudeInput.value === '' ||
                        !Number.isFinite(lat) ||
                        !Number.isFinite(lng) ||
                        lat < -90 ||
                        lat > 90 ||
                        lng < -180 ||
                        lng > 180
                    ) {
                        marker.remove();
                        return;
                    }

                    const position = [lat, lng];
                    marker.setLatLng(position).addTo(map);
                    map.panTo(position);
                };

                const focusOnQuartier = async () => {
                    const location = quartierLocations[quartierSelect.value];
                    if (!location) {
                        return;
                    }

                    const requestId = ++geocodingRequest;
                    geocodingController?.abort();
                    geocodingController = new AbortController();
                    const { signal } = geocodingController;
                    latitudeInput.value = '';
                    longitudeInput.value = '';
                    marker.remove();
                    statusElement.textContent = `Recherche du quartier ${location.quartier} sur OpenStreetMap…`;

                    try {
                        const search = async (name) => {
                            const params = new URLSearchParams({
                                format: 'jsonv2',
                                limit: '1',
                                countrycodes: 'sn',
                                q: `${name}, Sénégal`,
                            });
                            return requestNominatim(`/search?${params}`, signal);
                        };

                        let results = await search(`${location.quartier}, ${location.ville}`);
                        if (signal.aborted || requestId !== geocodingRequest) {
                            return;
                        }

                        let isCityFallback = false;
                        if (!results.length && location.ville) {
                            statusElement.textContent = `Quartier introuvable ; recherche de la ville ${location.ville}…`;
                            results = await search(location.ville);
                            isCityFallback = true;
                        }

                        if (signal.aborted || requestId !== geocodingRequest) {
                            return;
                        }

                        const result = results[0];
                        if (!result) {
                            statusElement.textContent = 'Lieu introuvable. Déplacez la carte manuellement puis cliquez pour choisir le point exact.';
                            return;
                        }

                        map.setView([Number(result.lat), Number(result.lon)], isCityFallback ? 13 : 16);
                        statusElement.textContent = isCityFallback
                            ? `Carte centrée sur la ville ${location.ville}. Cliquez sur l’emplacement exact pour placer le repère.`
                            : `Carte centrée sur ${location.quartier}. Cliquez sur l’emplacement exact pour placer le repère.`;
                    } catch (error) {
                        if (signal.aborted || requestId !== geocodingRequest) {
                            return;
                        }

                        statusElement.textContent = 'Impossible de localiser ce quartier. Vérifiez votre connexion ou choisissez le point manuellement.';
                        console.error('OpenStreetMap geocoding failed:', error);
                    }
                };

                const detectQuartier = async (position) => {
                    const requestId = ++geocodingRequest;
                    geocodingController?.abort();
                    geocodingController = new AbortController();
                    const { signal } = geocodingController;
                    statusElement.textContent = 'Recherche du quartier correspondant au point choisi…';

                    try {
                        const params = new URLSearchParams({
                            format: 'jsonv2',
                            lat: String(position.lat),
                            lon: String(position.lng),
                            zoom: '16',
                            addressdetails: '1',
                        });
                        const result = await requestNominatim(`/reverse?${params}`, signal);
                        if (signal.aborted || requestId !== geocodingRequest) {
                            return;
                        }

                        const address = result.address ?? {};
                        const neighborhoodNames = [
                            address.neighbourhood,
                            address.suburb,
                            address.quarter,
                            address.city_district,
                        ].filter(Boolean);
                        const cityNames = [
                            address.city,
                            address.town,
                            address.village,
                            address.municipality,
                            address.county,
                            address.state,
                        ].filter(Boolean);
                        const detectedNeighborhood = neighborhoodNames[0] ?? '';
                        const detectedCity = cityNames[0] ?? '';
                        const matchingQuartiers = Object.entries(quartierLocations).filter(([, location]) =>
                            neighborhoodNames.some((name) => normalizeName(name) === normalizeName(location.quartier)) &&
                            cityNames.some((name) => normalizeName(name) === normalizeName(location.ville ?? '')),
                        );

                        if (matchingQuartiers.length === 1) {
                            quartierSelect.value = matchingQuartiers[0][0];
                            statusElement.textContent = `Quartier détecté : ${detectedNeighborhood} — ${detectedCity}. Le quartier correspondant a été sélectionné.`;
                        } else if (matchingQuartiers.length > 1) {
                            statusElement.textContent = `Quartier détecté : ${detectedNeighborhood} — ${detectedCity}. Plusieurs correspondances : vérifiez le quartier sélectionné.`;
                        } else {
                            const detectedAddress = [detectedNeighborhood, detectedCity].filter(Boolean).join(' — ');
                            statusElement.textContent = detectedAddress
                                ? `Lieu détecté : ${detectedAddress}. Aucun quartier correspondant trouvé ; vérifiez le choix dans la liste.`
                                : `Lieu détecté : ${result.display_name}. Vérifiez le quartier choisi dans la liste.`;
                        }
                    } catch (error) {
                        if (signal.aborted || requestId !== geocodingRequest) {
                            return;
                        }

                        statusElement.textContent = quartierSelect.value
                            ? 'Impossible de détecter le quartier. Le quartier déjà choisi est conservé.'
                            : 'Impossible de détecter le quartier. Choisissez le quartier — ville dans la liste.';
                        console.error('OpenStreetMap reverse geocoding failed:', error);
                    }
                };

                const updatePosition = (position) => {
                    setCoordinateInputs(position.lat, position.lng);
                    marker.setLatLng(position).addTo(map);
                    detectQuartier(position);
                };

                const initializeMap = () => {
                    map = L.map(mapElement).setView(
                        [initialPosition.lat, initialPosition.lng],
                        hasInitialPosition ? 15 : 12,
                    );
                    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    }).addTo(map);
                    marker = L.marker([initialPosition.lat, initialPosition.lng], {
                        draggable: true,
                    });

                    if (hasInitialPosition) {
                        marker.addTo(map);
                        statusElement.textContent = 'Emplacement existant. Cliquez sur la carte ou déplacez le repère.';
                    } else if (quartierSelect.value) {
                        focusOnQuartier();
                    } else {
                        statusElement.textContent = 'Choisissez une ville et un quartier, puis cliquez sur la carte.';
                    }

                    map.on('click', (event) => updatePosition(event.latlng));
                    marker.on('dragend', (event) => updatePosition(event.target.getLatLng()));
                    quartierSelect.addEventListener('change', focusOnQuartier);
                    latitudeInput.addEventListener('change', setMarkerFromInputs);
                    longitudeInput.addEventListener('change', setMarkerFromInputs);
                };

                const stylesheet = document.createElement('link');
                stylesheet.rel = 'stylesheet';
                stylesheet.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                stylesheet.integrity = 'sha384-sHL9NAb7lN7rfvG5lfHpm643Xkcjzp4jFvuavGOndn6pjVqS6ny56CAt3nsEVT4H';
                stylesheet.crossOrigin = 'anonymous';

                const script = document.createElement('script');
                script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                script.integrity = 'sha384-cxOPjt7s7Iz04uaHJceBmS+qpjv2JkIHNVcuOrM+YHwZOmJGBXI00mdUXEq65HTH';
                script.crossOrigin = 'anonymous';
                script.async = true;
                script.defer = true;
                script.onload = initializeMap;
                script.onerror = () => { statusElement.textContent = 'Impossible de charger Leaflet. Vérifiez votre connexion.'; };
                stylesheet.onload = () => document.head.appendChild(script);
                stylesheet.onerror = () => { statusElement.textContent = 'Impossible de charger les styles de la carte. Vérifiez votre connexion.'; };
                document.head.appendChild(stylesheet);
            })();
        </script>
    @endpush
@endif

@if ($model === 'utilisateurs')
    @push('scripts')
        <script>
            (() => {
                const dialog = document.getElementById('profile-photo-dialog');
                const preview = document.getElementById('profile-photo-preview');
                const closeButton = document.getElementById('profile-photo-close');

                document.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-profile-photo]');

                    if (!button) {
                        return;
                    }

                    preview.src = button.dataset.profilePhoto;
                    preview.alt = button.dataset.profilePhotoAlt;
                    dialog.showModal();
                });

                closeButton.addEventListener('click', () => dialog.close());
                dialog.addEventListener('click', (event) => {
                    if (event.target === dialog) {
                        dialog.close();
                    }
                });
            })();
        </script>
    @endpush
@endif

@section('content')
    <section class="panel form-panel">
        <div class="panel-head">
            <h3>{{ $editing ? 'Modifier un enregistrement' : 'Ajouter un enregistrement' }}</h3>
        </div>

        <form action="{{ $editing ? route('admin.resource.update', ['model' => $model, 'id' => $editing->getKey()]) : route('admin.resource.store', ['model' => $model]) }}" method="POST" class="resource-form" @if ($model === 'utilisateurs') enctype="multipart/form-data" @endif>
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            @foreach ($fields as $fieldName => $field)
                @php
                    $value = $fieldName === 'mot_de_passe'
                        ? ''
                        : old($fieldName, $editing?->{$fieldName} ?? '');
                @endphp

                @if ($model === 'etablissements' && in_array($fieldName, ['latitude', 'longitude'], true))
                    @continue
                @endif

                <div class="field-group">
                    <label for="{{ $fieldName }}">{{ $field['label'] }}</label>

                    @if (($field['type'] ?? 'text') === 'textarea')
                        <textarea id="{{ $fieldName }}" name="{{ $fieldName }}" placeholder="{{ $field['placeholder'] ?? '' }}">{{ $value }}</textarea>
                    @elseif (($field['type'] ?? 'text') === 'select')
                        <select id="{{ $fieldName }}" name="{{ $fieldName }}">
                            <option value="">Choisir</option>
                            @foreach ($field['options'] ?? [] as $optionValue => $optionLabel)
                                <option value="{{ $optionValue }}" {{ (string) $value === (string) $optionValue ? 'selected' : '' }}>
                                    {{ $optionLabel }}
                                </option>
                            @endforeach
                        </select>
                    @elseif (($field['type'] ?? 'text') === 'image')
                        <input id="{{ $fieldName }}" type="file" name="{{ $fieldName }}" accept="image/jpeg,image/png,image/webp">
                        @if ($editing?->{$fieldName})
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($editing->{$fieldName}) }}" alt="Photo de profil actuelle" class="profile-photo">
                        @endif
                    @else
                        <input
                            id="{{ $fieldName }}"
                            type="{{ $field['type'] ?? 'text' }}"
                            name="{{ $fieldName }}"
                            value="{{ $value }}"
                            step="{{ $field['step'] ?? '' }}"
                            placeholder="{{ $fieldName === 'mot_de_passe' && $editing ? 'Laisser vide pour conserver le mot de passe' : ($field['placeholder'] ?? '') }}"
                            {{ ($field['type'] ?? 'text') === 'password' ? 'autocomplete="new-password"' : '' }}
                        >
                    @endif
                    @error($fieldName)
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            @if ($model === 'etablissements')
                <div class="field-group">
                    <label for="establishment-map">Emplacement sur la carte</label>
                    <p class="map-help">Cliquez sur la carte pour placer le repère. Vous pouvez aussi le déplacer.</p>
                    <div
                        id="establishment-map"
                        class="establishment-map"
                        role="region"
                        aria-label="Carte OpenStreetMap pour choisir l'emplacement de l'établissement"
                    ></div>
                    <p id="establishment-map-status" class="map-help" role="status" aria-live="polite"></p>
                </div>

                <div class="coordinates-grid">
                    <div class="field-group">
                        <label for="latitude">Latitude</label>
                        <input id="latitude" type="number" name="latitude" value="{{ old('latitude', $editing?->latitude ?? '') }}" step="any" min="-90" max="90" required>
                    </div>
                    <div class="field-group">
                        <label for="longitude">Longitude</label>
                        <input id="longitude" type="number" name="longitude" value="{{ old('longitude', $editing?->longitude ?? '') }}" step="any" min="-180" max="180" required>
                    </div>
                </div>
            @endif

            <div class="form-actions">
                <button type="submit" class="primary-btn">
                    {{ $editing ? 'Mettre à jour' : 'Enregistrer' }}
                </button>
                @if ($editing)
                    <a href="{{ route($routeName) }}" class="secondary-btn">Annuler</a>
                @endif
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h3>Liste des {{ Str::lower($title) }}</h3>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        @foreach ($fields as $fieldName => $field)
                            <th>{{ $field['label'] }}</th>
                        @endforeach
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            @foreach ($fields as $fieldName => $field)
                                <td>
                                    @php
                                        $cell = $record->{$fieldName} ?? null;
                                    @endphp

                                    @if (($field['type'] ?? 'text') === 'select' && isset($record->{$fieldName}))
                                        @php
                                            $relatedModel = null;
                                            if ($fieldName === 'id_ville') {
                                                $relatedModel = $record->ville;
                                            }
                                            elseif ($fieldName === 'id_quartier') {
                                                $relatedModel = $record->quartier;
                                            }
                                        @endphp
                                        {{ $relatedModel?->nom_quartier ?? $relatedModel?->nom_Ville ?? $cell }}
                                    @elseif (($field['type'] ?? 'text') === 'password')
                                        ••••••
                                    @elseif (($field['type'] ?? 'text') === 'image')
                                        @if ($cell)
                                            <button
                                                type="button"
                                                class="mini-btn edit profile-photo-button"
                                                data-profile-photo="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($cell) }}"
                                                data-profile-photo-alt="Photo de profil de {{ $record->prenom }} {{ $record->nom }}"
                                            >Voir la photo</button>
                                        @else
                                            -
                                        @endif
                                    @else
                                        {{ is_bool($cell) ? ($cell ? 'Oui' : 'Non') : ($cell ?? '-') }}
                                    @endif
                                </td>
                            @endforeach
                            <td class="actions-cell">
                                <a href="{{ route($routeName, ['edit' => $record->getKey()]) }}" class="mini-btn edit">Modifier</a>
                                <form action="{{ route('admin.resource.destroy', ['model' => $model, 'id' => $record->getKey()]) }}" method="POST" onsubmit="return confirm('Voulez-vous vraiment supprimer cet élément ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="mini-btn delete">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($fields) + 1 }}" class="empty-text">Aucune donnée disponible.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($model === 'utilisateurs')
        <dialog id="profile-photo-dialog" class="profile-photo-dialog" aria-label="Photo de profil">
            <button id="profile-photo-close" type="button" class="mini-btn delete profile-photo-close" aria-label="Fermer l’affichage de la photo">Fermer</button>
            <img id="profile-photo-preview" class="profile-photo-preview" src="" alt="">
        </dialog>
    @endif
@endsection
