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
                const apiKey = mapElement.dataset.apiKey;
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

                if (!apiKey) {
                    statusElement.textContent = 'Configurez GOOGLE_MAPS_API_KEY dans le fichier .env pour afficher la carte.';
                    return;
                }

                window.gm_authFailure = () => {
                    statusElement.textContent = 'La clé Google Maps est refusée. Vérifiez son activation, la facturation et ses restrictions de domaine.';
                };

                window.initEstablishmentMap = () => {
                    const map = new google.maps.Map(mapElement, {
                        center: initialPosition,
                        zoom: hasInitialPosition ? 15 : 12,
                        mapTypeControl: false,
                    });
                    const marker = new google.maps.Marker({
                        map: hasInitialPosition ? map : null,
                        position: hasInitialPosition ? initialPosition : null,
                        draggable: true,
                    });
                    const geocoder = new google.maps.Geocoder();
                    const mapTypeButton = document.getElementById('map-type-toggle');
                    let geocodingRequest = 0;
                    let satelliteView = false;

                    mapTypeButton.addEventListener('click', () => {
                        satelliteView = !satelliteView;
                        map.setMapTypeId(satelliteView ? google.maps.MapTypeId.SATELLITE : google.maps.MapTypeId.ROADMAP);
                        mapTypeButton.textContent = satelliteView ? 'Revenir au plan' : 'Afficher la vue satellite';
                        mapTypeButton.setAttribute('aria-pressed', String(satelliteView));
                    });

                    const updateCoordinates = (position) => {
                        latitudeInput.value = position.lat().toFixed(7);
                        longitudeInput.value = position.lng().toFixed(7);
                        marker.setPosition(position);
                        marker.setMap(map);
                    };

                    const focusOnQuartier = async () => {
                        const location = quartierLocations[quartierSelect.value];
                        if (!location) {
                            return;
                        }

                        const requestId = ++geocodingRequest;
                        latitudeInput.value = '';
                        longitudeInput.value = '';
                        marker.setMap(null);
                        statusElement.textContent = `Recherche du quartier ${location.quartier} sur Google Maps…`;

                        try {
                            let response = await geocoder.geocode({
                                address: `${location.quartier}, ${location.ville}, Sénégal`,
                                componentRestrictions: { country: 'SN' },
                            });

                            if (requestId !== geocodingRequest) {
                                return;
                            }

                            let isCityFallback = false;
                            if (!response.results.length && location.ville) {
                                statusElement.textContent = `Quartier introuvable ; recherche de la ville ${location.ville}…`;
                                response = await geocoder.geocode({
                                    address: `${location.ville}, Sénégal`,
                                    componentRestrictions: { country: 'SN' },
                                });
                                isCityFallback = true;
                            }

                            if (requestId !== geocodingRequest) {
                                return;
                            }

                            if (!response.results.length) {
                                statusElement.textContent = 'Lieu introuvable. Déplacez la carte manuellement puis cliquez pour choisir le point exact.';
                                return;
                            }

                            map.setCenter(response.results[0].geometry.location);
                            map.setZoom(isCityFallback ? 13 : 16);
                            statusElement.textContent = isCityFallback
                                ? `Carte centrée sur la ville ${location.ville}. Cliquez sur l’emplacement exact pour placer le repère.`
                                : `Carte centrée sur ${location.quartier}. Cliquez sur l’emplacement exact pour placer le repère.`;
                        } catch (error) {
                            if (requestId !== geocodingRequest) {
                                return;
                            }

                            statusElement.textContent = 'Impossible de localiser ce quartier. Vérifiez l’activation de l’API Geocoding ou choisissez le point manuellement.';
                            console.error('Google Maps geocoding failed:', error);
                        }
                    };

                    const normalizeName = (name) => name
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .toLocaleLowerCase('fr')
                        .replace(/[^a-z0-9]+/g, ' ')
                        .trim();

                    const detectQuartier = async (position) => {
                        const requestId = ++geocodingRequest;
                        statusElement.textContent = 'Recherche du quartier correspondant au point choisi…';

                        try {
                            const response = await geocoder.geocode({ location: position });

                            if (requestId !== geocodingRequest) {
                                return;
                            }

                            const result = response.results.find((candidate) =>
                                candidate.address_components.some((component) =>
                                    component.types.some((type) => [
                                        'neighborhood',
                                        'sublocality',
                                        'sublocality_level_1',
                                        'sublocality_level_2',
                                    ].includes(type)),
                                ),
                            ) ?? response.results[0];

                            if (!result) {
                                statusElement.textContent = quartierSelect.value
                                    ? 'Quartier non détecté à cette position. Le quartier déjà choisi est conservé.'
                                    : 'Quartier non détecté à cette position. Choisissez le quartier — ville dans la liste.';
                                return;
                            }

                            const components = response.results.flatMap((candidate) => candidate.address_components);
                            const neighborhoodTypes = [
                                'neighborhood',
                                'sublocality',
                                'sublocality_level_1',
                                'sublocality_level_2',
                            ];
                            const cityTypes = [
                                'locality',
                                'postal_town',
                                'administrative_area_level_2',
                                'administrative_area_level_1',
                            ];
                            const neighborhoodNames = [...new Set(components
                                .filter((component) => component.types.some((type) => neighborhoodTypes.includes(type)))
                                .map((component) => component.long_name))];
                            const cityNames = [...new Set(components
                                .filter((component) => component.types.some((type) => cityTypes.includes(type)))
                                .map((component) => component.long_name))];
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
                                statusElement.textContent = `Quartier détecté : ${detectedNeighborhood} — ${detectedCity}. Plusieurs correspondances : le choix actuel est conservé, vérifiez-le dans la liste.`;
                            } else {
                                const detectedAddress = [detectedNeighborhood, detectedCity].filter(Boolean).join(' — ');
                                statusElement.textContent = detectedAddress
                                    ? `Lieu détecté : ${detectedAddress}. Aucun quartier correspondant trouvé ; le choix actuel est conservé. Vérifiez-le dans la liste.`
                                    : `Lieu détecté : ${result.formatted_address}. Le choix actuel est conservé ; vérifiez-le dans la liste.`;
                            }
                        } catch (error) {
                            if (requestId !== geocodingRequest) {
                                return;
                            }

                            statusElement.textContent = quartierSelect.value
                                ? 'Impossible de détecter le quartier. Le quartier déjà choisi est conservé.'
                                : 'Impossible de détecter le quartier. Choisissez le quartier — ville dans la liste.';
                            console.error('Google Maps reverse geocoding failed:', error);
                        }
                    };

                    map.addListener('click', (event) => {
                        updateCoordinates(event.latLng);
                        detectQuartier(event.latLng);
                    });
                    marker.addListener('dragend', (event) => {
                        const position = event.latLng;
                        updateCoordinates(position);
                        detectQuartier(position);
                    });
                    quartierSelect.addEventListener('change', focusOnQuartier);

                    const updateMarkerFromInputs = () => {
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
                            marker.setMap(null);
                            return;
                        }

                        const position = { lat, lng };
                        marker.setPosition(position);
                        marker.setMap(map);
                        map.panTo(position);
                    };

                    latitudeInput.addEventListener('change', updateMarkerFromInputs);
                    longitudeInput.addEventListener('change', updateMarkerFromInputs);

                    if (!hasInitialPosition && quartierSelect.value) {
                        focusOnQuartier();
                    } else {
                        statusElement.textContent = 'Cliquez sur la carte pour choisir l’emplacement.';
                    }
                };

                const script = document.createElement('script');
                script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&callback=initEstablishmentMap&v=weekly`;
                script.async = true;
                script.defer = true;
                script.onerror = () => {
                    statusElement.textContent = 'Impossible de charger Google Maps. Vérifiez votre connexion, la clé API et ses restrictions.';
                };
                document.head.appendChild(script);
            })();
        </script>
    @endpush
@endif

@section('content')
    <section class="panel form-panel">
        <div class="panel-head">
            <h3>{{ $editing ? 'Modifier un enregistrement' : 'Ajouter un enregistrement' }}</h3>
        </div>

        <form action="{{ $editing ? route('admin.resource.update', ['model' => $model, 'id' => $editing->getKey()]) : route('admin.resource.store', ['model' => $model]) }}" method="POST" class="resource-form">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            @foreach ($fields as $fieldName => $field)
                @php
                    $value = old($fieldName, $editing?->{$fieldName} ?? '');
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
                    @else
                        <input
                            id="{{ $fieldName }}"
                            type="{{ $field['type'] ?? 'text' }}"
                            name="{{ $fieldName }}"
                            value="{{ $value }}"
                            step="{{ $field['step'] ?? '' }}"
                            placeholder="{{ $field['placeholder'] ?? '' }}"
                            {{ ($field['type'] ?? 'text') === 'password' ? 'autocomplete="new-password"' : '' }}
                        >
                    @endif
                </div>
            @endforeach

            @if ($model === 'etablissements')
                <div class="field-group">
                    <label for="establishment-map">Emplacement sur la carte</label>
                    <p class="map-help">Cliquez sur la carte pour placer le repère. Vous pouvez aussi le déplacer.</p>
                    <button id="map-type-toggle" type="button" class="secondary-btn map-toggle" aria-pressed="false">
                        Afficher la vue satellite
                    </button>
                    <div
                        id="establishment-map"
                        class="establishment-map"
                        data-api-key="{{ config('services.google_maps.key') }}"
                        data-latitude="{{ old('latitude', $editing?->latitude ?? '') }}"
                        data-longitude="{{ old('longitude', $editing?->longitude ?? '') }}"
                        role="region"
                        aria-label="Carte Google Maps pour choisir l'emplacement de l'établissement"
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

                                    @if ($field['type'] ?? 'text' === 'select' && isset($record->{$fieldName}))
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

@endsection
