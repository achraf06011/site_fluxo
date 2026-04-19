import React from "react";
import { DefaultTheme, ThemeProvider } from "@react-navigation/native";
import { Stack } from "expo-router";
import { StatusBar } from "expo-status-bar";
import { Modal, View, Text, StyleSheet, TouchableOpacity } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import "react-native-reanimated";
import {
  NotificationsProvider,
  useNotifications,
} from "../context/notifications-context";

export const unstable_settings = {
  anchor: "(tabs)",
};

function NotificationPopup() {
  const { popup, popupVisible, closePopup, openPopup } = useNotifications();

  return (
    <Modal
      visible={popupVisible}
      transparent
      animationType="fade"
      onRequestClose={closePopup}
    >
      <View style={styles.overlay}>
        <TouchableOpacity
          activeOpacity={1}
          style={styles.popupCard}
          onPress={openPopup}
        >
          <TouchableOpacity
            style={styles.popupClose}
            onPress={(e) => {
              e.stopPropagation?.();
              closePopup();
            }}
          >
            <Ionicons name="close" size={22} color="#6b7280" />
          </TouchableOpacity>

          <View style={styles.popupRow}>
            <View
              style={[
                styles.popupIconWrap,
                { backgroundColor: popup?.icon_bg || "#f3f4f6" },
              ]}
            >
              <Ionicons
                name={(popup?.icon as any) || "notifications-outline"}
                size={24}
                color={popup?.icon_color || "#6b7280"}
              />
            </View>

            <View style={{ flex: 1 }}>
              <Text style={styles.popupTitle}>
                {popup?.titre || "Nouvelle notification"}
              </Text>

              <Text style={styles.popupText}>{popup?.contenu || ""}</Text>

              <Text style={styles.popupHint}>
                Appuie sur la notification pour ouvrir
              </Text>
            </View>
          </View>
        </TouchableOpacity>
      </View>
    </Modal>
  );
}

function RootNavigator() {
  return (
    <>
      <Stack
        screenOptions={{
          contentStyle: { backgroundColor: "#ffffff" },
          headerStyle: {
            backgroundColor: "#ffffff",
          },
          headerTintColor: "#111827",
          headerTitleStyle: {
            color: "#111827",
            fontWeight: "800",
          },
          headerShadowVisible: true,
          headerBackButtonDisplayMode: "minimal",
          headerBackTitle: "",
        }}
      >
        <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
        <Stack.Screen
          name="annonce/[id]"
          options={{ title: "Détail annonce" }}
        />
        <Stack.Screen name="messages" options={{ title: "Messages" }} />
        <Stack.Screen name="login" options={{ title: "Connexion" }} />
        <Stack.Screen name="register" options={{ title: "Inscription" }} />
        <Stack.Screen
          name="verifier-email"
          options={{ title: "Vérifier email" }}
        />
        <Stack.Screen
          name="forgot-password"
          options={{ title: "Mot de passe oublié" }}
        />
        <Stack.Screen
          name="reset-password"
          options={{ title: "Nouveau mot de passe" }}
        />
        <Stack.Screen name="favoris" options={{ title: "Favoris" }} />
        <Stack.Screen
          name="annonce-edit/[id]"
          options={{ title: "Modifier annonce" }}
        />
        <Stack.Screen name="publier" options={{ title: "Publier annonce" }} />
        <Stack.Screen name="panier" options={{ title: "Panier" }} />
        <Stack.Screen name="checkout" options={{ title: "Checkout" }} />
        <Stack.Screen name="dashboard" options={{ title: "Dashboard" }} />
        <Stack.Screen
          name="mes-commandes"
          options={{ title: "Mes commandes" }}
        />
        <Stack.Screen name="mes-ventes" options={{ title: "Mes ventes" }} />
        <Stack.Screen name="mes-annonces" options={{ title: "Mes annonces" }} />
        <Stack.Screen name="mon-compte" options={{ title: "Mon compte" }} />
        <Stack.Screen
          name="notifications"
          options={{ title: "Notifications" }}
        />
        <Stack.Screen name="commande/[id]" options={{ title: "Commande" }} />
        <Stack.Screen
          name="suivi-commande/[id]"
          options={{ title: "Suivi commande" }}
        />
        <Stack.Screen
          name="laisser-avis"
          options={{ title: "Laisser un avis" }}
        />
        <Stack.Screen name="vente/[id]" options={{ title: "Vente" }} />
        <Stack.Screen
          name="menu"
          options={{
            presentation: "transparentModal",
            headerShown: false,
            animation: "fade",
          }}
        />
        <Stack.Screen
          name="modal"
          options={{ presentation: "modal", title: "Modal" }}
        />
      </Stack>

      <NotificationPopup />
      <StatusBar style="dark" />
    </>
  );
}

export default function RootLayout() {
  return (
    <ThemeProvider value={DefaultTheme}>
      <NotificationsProvider>
        <RootNavigator />
      </NotificationsProvider>
    </ThemeProvider>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: "rgba(15,23,42,0.18)",
    justifyContent: "flex-start",
    alignItems: "flex-end",
    paddingTop: 96,
    paddingHorizontal: 14,
  },
  popupCard: {
    width: 320,
    maxWidth: "100%",
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 16,
    shadowColor: "#000",
    shadowOpacity: 0.14,
    shadowRadius: 14,
    shadowOffset: { width: 0, height: 8 },
    elevation: 8,
  },
  popupClose: {
    alignSelf: "flex-end",
    marginBottom: 6,
    zIndex: 5,
  },
  popupRow: {
    flexDirection: "row",
    gap: 12,
  },
  popupIconWrap: {
    width: 52,
    height: 52,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  popupTitle: {
    fontSize: 22,
    fontWeight: "900",
    color: "#1e3a8a",
    marginBottom: 6,
  },
  popupText: {
    fontSize: 16,
    color: "#6b7280",
    lineHeight: 22,
  },
  popupHint: {
    marginTop: 12,
    fontSize: 13,
    fontWeight: "700",
    color: "#2563eb",
  },
});
