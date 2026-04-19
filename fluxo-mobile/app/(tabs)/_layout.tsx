import { Tabs, router } from "expo-router";
import React, { useEffect, useState } from "react";
import { Pressable, View, Image, Text, StyleSheet } from "react-native";
import { Ionicons } from "@expo/vector-icons";

import { HapticTab } from "@/components/haptic-tab";
import { IconSymbol } from "@/components/ui/icon-symbol";
import { getUser } from "../../utils/auth";

const API_BASE = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/api";

export default function TabLayout() {
  const [hasUnreadNotif, setHasUnreadNotif] = useState(false);

  async function loadNotifBadge() {
    try {
      const user = await getUser();

      if (!user?.id_user) {
        setHasUnreadNotif(false);
        return;
      }

      const res = await fetch(
        `${API_BASE}/notifications_mobile.php?user_id=${Number(user.id_user)}`,
        {
          headers: {
            Accept: "application/json",
          },
        }
      );

      const text = await res.text();

      if (!text || !text.trim()) {
        setHasUnreadNotif(false);
        return;
      }

      const data = JSON.parse(text);

      if (!data.ok) {
        setHasUnreadNotif(false);
        return;
      }

      const items = Array.isArray(data.notifications) ? data.notifications : [];
      const unreadExists = items.some((item: any) => Number(item.is_read || 0) === 0);

      setHasUnreadNotif(unreadExists);
    } catch (e) {
      setHasUnreadNotif(false);
    }
  }

  useEffect(() => {
    loadNotifBadge();

    const interval = setInterval(() => {
      loadNotifBadge();
    }, 5000);

    return () => clearInterval(interval);
  }, []);

  return (
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: "#0891b2",
        tabBarInactiveTintColor: "#6b7280",
        tabBarButton: HapticTab,

        headerShown: true,
        headerStyle: {
          backgroundColor: "#ffffff",
        },
        headerShadowVisible: true,
        headerTitleAlign: "left",

        headerTitle: () => (
          <View style={styles.headerBrand}>
            <Image
              source={require("../../assets/images/logo.png")}
              style={styles.headerLogo}
            />
            <Text style={styles.headerTitle}>Fluxo</Text>
          </View>
        ),

        headerRight: () => (
          <Pressable onPress={() => router.push("/menu")} style={styles.menuBtnWrap}>
            <View style={styles.menuBtn}>
              <Ionicons name="menu-outline" size={26} color="#374151" />
            </View>

            {hasUnreadNotif ? <View style={styles.redDot} /> : null}
          </Pressable>
        ),

        tabBarStyle: {
          backgroundColor: "#ffffff",
          borderTopWidth: 1,
          borderTopColor: "#e5e7eb",
          height: 62,
          paddingBottom: 8,
          paddingTop: 6,
        },

        tabBarLabelStyle: {
          fontSize: 12,
          fontWeight: "700",
        },
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: "Accueil",
          tabBarIcon: ({ color }) => (
            <IconSymbol size={28} name="house.fill" color={color} />
          ),
        }}
      />

      <Tabs.Screen
        name="explore"
        options={{
          title: "Explore",
          tabBarIcon: ({ color }) => (
            <IconSymbol size={28} name="paperplane.fill" color={color} />
          ),
        }}
      />
    </Tabs>
  );
}

const styles = StyleSheet.create({
  headerBrand: {
    flexDirection: "row",
    alignItems: "center",
  },
  headerLogo: {
    width: 80,
    height: 80,
    resizeMode: "contain",
    marginRight: 10,
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: "900",
    color: "#111827",
  },
  menuBtnWrap: {
    marginRight: 14,
    position: "relative",
  },
  menuBtn: {
    width: 40,
    height: 40,
    borderWidth: 1.5,
    borderColor: "#9ca3af",
    borderRadius: 14,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#ffffff",
  },
  redDot: {
    position: "absolute",
    top: 2,
    right: 2,
    width: 9,
    height: 9,
    borderRadius: 999,
    backgroundColor: "#ef4444",
    borderWidth: 1.5,
    borderColor: "#ffffff",
  },
});