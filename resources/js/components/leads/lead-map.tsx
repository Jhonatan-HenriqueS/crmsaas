import L from 'leaflet';
import type { LatLngTuple } from 'leaflet';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';
import 'leaflet/dist/leaflet.css';
import { useEffect } from 'react';
import { MapContainer, Marker, TileLayer, useMap } from 'react-leaflet';

L.Icon.Default.imagePath = '';
L.Icon.Default.mergeOptions({ iconRetinaUrl, iconUrl, shadowUrl });

function InvalidateSize({ ready }: { ready: boolean }) {
    const map = useMap();

    useEffect(() => {
        if (ready) map.invalidateSize();
    }, [map, ready]);

    return null;
}

export function LeadMap({
    position,
    ready,
}: {
    position: LatLngTuple;
    ready: boolean;
}) {
    return (
        <MapContainer
            center={position}
            zoom={16}
            scrollWheelZoom
            className="isolate h-full w-full"
        >
            <TileLayer
                attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                url="https://tile.openstreetmap.org/{z}/{x}/{y}.png"
            />
            <Marker position={position} />
            <InvalidateSize ready={ready} />
        </MapContainer>
    );
}
