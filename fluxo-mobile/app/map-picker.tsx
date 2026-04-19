import React, { useMemo, useState } from "react";
import { View, StyleSheet, TouchableOpacity, Text, Alert } from "react-native";
import MapView, { Marker, MapPressEvent } from "react-native-maps";
import { Stack, router, useLocalSearchParams } from "expo-router";

export default function MapPickerScreen() {
  const params = useLocalSearchParams<{
    id?: string;
    latitude?: string;
    longitude?: string;
  }>();

  const initialLatitude = Number(params.latitude || 31.6295);
  const initialLongitude = Number(params.longitude || -7.9811);

  const initialRegion = useMemo(
    () => ({
      latitude: isNaN(initialLatitude) ? 31.6295 : initialLatitude,
      longitude: isNaN(initialLongitude) ? -7.9811 : initialLongitude,
      latitudeDelta: 0.08,
      longitudeDelta: 0.08,
    }),
    [initialLatitude, initialLongitude]
  );

  const [marker, setMarker] = useState({
    latitude: initialRegion.latitude,
    longitude: initialRegion.longitude,
  });

  function onMapPress(e: MapPressEvent) {
    const { latitude, longitude } = e.nativeEvent.coordinate;
    setMarker({ latitude, longitude });
  }

  function validatePosition() {
    if (!params.id) {
      Alert.alert("Erreur", "Annonce introuvable.");
      return;
    }

    router.replace({
      pathname: "/annonce-edit/[id]",
      params: {
        id: String(params.id),
        latitude: String(marker.latitude),
        longitude: String(marker.longitude),
      },
    });
  }

  return (
    <>
      <Stack.Screen options={{ title: "Choisir localisation" }} />

      <View style={styles.container}>
        <MapView style={styles.map} initialRegion={initialRegion} onPress={onMapPress}>
          <Marker coordinate={marker} />
        </MapView>

        <View style={styles.footer}>
          <Text style={styles.coords}>
            Lat: {marker.latitude.toFixed(6)} | Lng: {marker.longitude.toFixed(6)}
          </Text>

          <TouchableOpacity style={styles.primaryBtn} onPress={validatePosition}>
            <Text style={styles.primaryBtnText}>Valider cette position</Text>
          </TouchableOpacity>
        </View>
      </View>
    </>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#fff",
  },
  map: {
    flex: 1,
  },
  footer: {
    padding: 16,
    borderTopWidth: 1,
    borderTopColor: "#e5e7eb",
    backgroundColor: "#fff",
  },
  coords: {
    fontSize: 14,
    color: "#374151",
    marginBottom: 12,
    fontWeight: "700",
  },
  primaryBtn: {
    backgroundColor: "#2563eb",
    borderRadius: 14,
    paddingVertical: 15,
    alignItems: "center",
  },
  primaryBtnText: {
    color: "#fff",
    fontWeight: "800",
    fontSize: 16,
  },
});