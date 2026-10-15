let chargementGoogleMaps;

function preparerChargeurGoogleMaps(cleApi) {
  const google = window.google || (window.google = {});
  const cartes = google.maps || (google.maps = {});

  if (typeof cartes.importLibrary === "function") {
    return cartes;
  }

  const bibliothequesEnAttente = new Set();
  let promesseScript;
  const nomBibliotheque = "importLibrary";

  cartes[nomBibliotheque] = (bibliotheque, ...argumentsBibliotheque) => {
    bibliothequesEnAttente.add(bibliotheque);
    promesseScript ||= new Promise((resolve, reject) => {
      const script = document.createElement("script");
      const parametres = new URLSearchParams({
        key: cleApi,
        v: "weekly",
        libraries: [...bibliothequesEnAttente].join(","),
        callback: "google.maps.__ib__",
      });

      cartes.__ib__ = resolve;
      script.onerror = () => {
        reject(new Error("Google Maps n’a pas pu charger son script. Vérifiez la clé API et ses restrictions."));
      };
      script.nonce = document.querySelector("script[nonce]")?.nonce || "";
      script.src = `https://maps.googleapis.com/maps/api/js?${parametres}`;
      script.async = true;
      script.defer = true;
      document.head.append(script);
    });

    return promesseScript.then(() => {
      if (typeof cartes[nomBibliotheque] !== "function") {
        throw new Error("Google Maps a chargé le script sans initialiser son chargeur de bibliothèques.");
      }

      return cartes[nomBibliotheque](bibliotheque, ...argumentsBibliotheque);
    });
  };

  return cartes;
}

export async function obtenirBibliothequesGoogleMaps() {
  const cleApi = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;

  if (!cleApi) {
    throw new Error("La clé Google Maps manque : configurez VITE_GOOGLE_MAPS_API_KEY dans frontend/.env.");
  }

  const cartesDejaChargees = window.google?.maps;
  if (
    cartesDejaChargees?.Map
    && cartesDejaChargees?.Geocoder
    && cartesDejaChargees?.Marker
    && cartesDejaChargees?.DirectionsService
    && cartesDejaChargees?.DirectionsRenderer
    && cartesDejaChargees?.TravelMode
  ) {
    return {
      Carte: cartesDejaChargees.Map,
      Geocodeur: cartesDejaChargees.Geocoder,
      Marqueur: cartesDejaChargees.Marker,
      ServiceItineraire: cartesDejaChargees.DirectionsService,
      AfficheurItineraire: cartesDejaChargees.DirectionsRenderer,
      ModesDeplacement: cartesDejaChargees.TravelMode,
    };
  }

  chargementGoogleMaps ||= (async () => {
    const googleMaps = preparerChargeurGoogleMaps(cleApi);
    const [cartes, geocodage, itineraire] = await Promise.all([
      googleMaps.importLibrary("maps"),
      googleMaps.importLibrary("geocoding"),
      googleMaps.importLibrary("routes"),
    ]);

    return {
      Carte: cartes.Map,
      Geocodeur: geocodage.Geocoder,
      Marqueur: googleMaps.Marker,
      ServiceItineraire: itineraire.DirectionsService,
      AfficheurItineraire: itineraire.DirectionsRenderer,
      ModesDeplacement: window.google.maps.TravelMode,
    };
  })().catch((erreur) => {
    chargementGoogleMaps = null;
    throw erreur;
  });

  return chargementGoogleMaps;
}
