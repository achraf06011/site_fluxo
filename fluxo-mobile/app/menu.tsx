import React, { useCallback, useState } from "react";
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Pressable,
  Image,
  Alert,
} from "react-native";
import { router, useFocusEffect } from "expo-router";
import { Ionicons } from "@expo/vector-icons";
import { getUser, logoutUser } from "../utils/auth";
import { useNotifications } from "../context/notifications-context";

export default function MenuScreen() {
  const { badgeCount } = useNotifications();
  const [user, setUser] = useState<any>(null);
  const isLoggedIn = !!user?.id_user;

  useFocusEffect(
    useCallback(() => {
      let active = true;

      async function loadUser() {
        const currentUser = await getUser();
        if (active) {
          setUser(currentUser);
        }
      }

      loadUser();

      return () => {
        active = false;
      };
    }, [])
  );

  function goToPublic(path: string) {
    router.back();
    setTimeout(() => {
      router.push(path as any);
    }, 120);
  }

  function askLogin() {
    Alert.alert(
      "Connexion requise",
      "Tu dois te connecter pour accéder à cette page.",
      [
        { text: "Annuler", style: "cancel" },
        {
          text: "Connexion",
          onPress: () => {
            router.back();
            setTimeout(() => {
              router.push("/login");
            }, 120);
          },
        },
      ]
    );
  }

  function goToProtected(path: string) {
    if (!isLoggedIn) {
      askLogin();
      return;
    }

    router.back();
    setTimeout(() => {
      router.push(path as any);
    }, 120);
  }

  async function doLogout() {
    try {
      await logoutUser();
      setUser(null);

      router.dismissAll();
      router.replace("/(tabs)");
    } catch (e) {
      Alert.alert("Erreur", "Impossible de se déconnecter.");
    }
  }

  return (
    <View style={styles.overlay}>
      <Pressable style={styles.backdrop} onPress={() => router.back()} />

      <View style={styles.panel}>
        <View style={styles.topRow}>
          <View style={styles.brandWrap}>
            <Image
              source={require("../assets/images/logo.png")}
              style={styles.logo}
            />
            <Text style={styles.logoText}>Fluxo</Text>
          </View>

          <TouchableOpacity
            style={styles.closeBtn}
            onPress={() => router.back()}
          >
            <Ionicons name="close" size={30} color="#4b5563" />
          </TouchableOpacity>
        </View>

        <TouchableOpacity style={styles.item} onPress={() => goToPublic("/(tabs)")}>
          <Text style={styles.itemText}>Annonces</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.item}
          onPress={() => goToProtected("/dashboard")}
        >
          <Text style={styles.itemText}>Dashboard</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.item}
          onPress={() => goToProtected("/mes-commandes")}
        >
          <Text style={styles.itemText}>Mes commandes</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.item}
          onPress={() => goToProtected("/mes-ventes")}
        >
          <Text style={styles.itemText}>Ventes</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.item}
          onPress={() => goToProtected("/favoris")}
        >
          <Text style={styles.itemText}>Favoris</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.item}
          onPress={() => goToProtected("/publier")}
        >
          <Text style={styles.itemText}>Publier</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.item}
          onPress={() => goToProtected("/panier")}
        >
          <Text style={styles.itemText}>Panier</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.item}
          onPress={() => goToProtected("/messages")}
        >
          <Text style={styles.itemText}>Messages</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.item}
          onPress={() => goToProtected("/notifications")}
        >
          <View style={styles.itemRow}>
            <Text style={styles.itemText}>Notifications</Text>

            {isLoggedIn && badgeCount > 0 ? (
              <View style={styles.badge}>
                <Text style={styles.badgeText}>
                  {badgeCount > 99 ? "99+" : badgeCount}
                </Text>
              </View>
            ) : null}
          </View>
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.item}
          onPress={() => goToProtected("/mon-compte")}
        >
          <Text style={styles.itemText}>Mon compte</Text>
        </TouchableOpacity>

        {!isLoggedIn ? (
          <>
            <TouchableOpacity
              style={styles.loginBtn}
              onPress={() => {
                router.back();
                setTimeout(() => {
                  router.push("/login");
                }, 120);
              }}
            >
              <Text style={styles.loginText}>Connexion</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.registerBtn}
              onPress={() => {
                router.back();
                setTimeout(() => {
                  router.push("/register");
                }, 120);
              }}
            >
              <Text style={styles.registerText}>Créer un compte</Text>
            </TouchableOpacity>
          </>
        ) : (
          <TouchableOpacity style={styles.logoutBtn} onPress={doLogout}>
            <Text style={styles.logoutText}>Déconnexion</Text>
          </TouchableOpacity>
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    flexDirection: "row",
    backgroundColor: "rgba(0,0,0,0.18)",
  },
  backdrop: {
    flex: 1,
  },
  panel: {
    width: "78%",
    maxWidth: 420,
    backgroundColor: "#fff",
    paddingTop: 28,
    paddingHorizontal: 24,
    paddingBottom: 32,
    borderTopLeftRadius: 24,
    borderBottomLeftRadius: 24,
  },
  topRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 26,
  },
  brandWrap: {
    flexDirection: "row",
    alignItems: "center",
  },
  logo: {
    width: 72,
    height: 72,
    resizeMode: "contain",
    marginRight: 8,
  },
  logoText: {
    fontSize: 22,
    fontWeight: "900",
    color: "#111827",
  },
  closeBtn: {
    width: 50,
    height: 50,
    borderRadius: 16,
    borderWidth: 1.5,
    borderColor: "#9ca3af",
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#fff",
  },
  item: {
    paddingVertical: 16,
    borderBottomWidth: 1,
    borderBottomColor: "#e5e7eb",
  },
  itemRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  itemText: {
    fontSize: 18,
    fontWeight: "700",
    color: "#374151",
  },
  badge: {
    minWidth: 28,
    height: 28,
    borderRadius: 999,
    backgroundColor: "#ec4899",
    paddingHorizontal: 8,
    alignItems: "center",
    justifyContent: "center",
  },
  badgeText: {
    color: "#fff",
    fontSize: 12,
    fontWeight: "900",
  },
  loginBtn: {
    marginTop: 22,
    backgroundColor: "#111827",
    borderRadius: 16,
    paddingVertical: 18,
    alignItems: "center",
    justifyContent: "center",
  },
  loginText: {
    color: "#fff",
    fontSize: 18,
    fontWeight: "800",
  },
  registerBtn: {
    marginTop: 12,
    backgroundColor: "#fff",
    borderRadius: 16,
    paddingVertical: 18,
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 1.5,
    borderColor: "#d1d5db",
  },
  registerText: {
    color: "#374151",
    fontSize: 18,
    fontWeight: "800",
  },
  logoutBtn: {
    marginTop: 22,
    backgroundColor: "#2563eb",
    borderRadius: 16,
    paddingVertical: 18,
    alignItems: "center",
    justifyContent: "center",
  },
  logoutText: {
    color: "#fff",
    fontSize: 18,
    fontWeight: "800",
  },
});