import React, { useCallback, useEffect, useState } from "react";
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
} from "react-native";
import { Stack, router, useFocusEffect } from "expo-router";
import { Ionicons } from "@expo/vector-icons";
import { getUser } from "../utils/auth";
import { useNotifications } from "../context/notifications-context";

const API_BASE = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/api";

type NotificationItem = {
  id_notification: number;
  titre: string;
  contenu: string;
  created_at: string;
  is_read: number;
  href?: string;
  icon?: string;
  extra_class?: string;
  mobile_route?: string;
  type_notification?: string;
};

export default function NotificationsScreen() {
  const { refreshNotifications } = useNotifications();

  const [loading, setLoading] = useState(true);
  const [errorMsg, setErrorMsg] = useState("");
  const [user, setUser] = useState<any>(null);
  const [items, setItems] = useState<NotificationItem[]>([]);

  const iconConfig = (type?: string) => {
    const t = String(type || "").toUpperCase();

    switch (t) {
      case "NEW_ORDER":
        return { icon: "bag-handle-outline", color: "#2563eb", bg: "#dbeafe" };
      case "ORDER_UPDATE":
      case "ORDER_STATUS":
        return { icon: "car-outline", color: "#d97706", bg: "#fef3c7" };
      case "ORDER_DELIVERED":
      case "ANNONCE_VALIDATED":
        return {
          icon: "checkmark-circle-outline",
          color: "#16a34a",
          bg: "#dcfce7",
        };
      case "ANNONCE_REFUSED":
      case "ANNONCE_DELETED":
        return {
          icon: "close-circle-outline",
          color: "#dc2626",
          bg: "#fee2e2",
        };
      case "NEW_MESSAGE":
        return {
          icon: "chatbubble-ellipses-outline",
          color: "#0891b2",
          bg: "#cffafe",
        };
      case "NEW_REVIEW":
        return { icon: "star-outline", color: "#d97706", bg: "#fef3c7" };
      case "ADMIN_ANNONCE_WAIT":
        return { icon: "hourglass-outline", color: "#d97706", bg: "#fef3c7" };
      case "ADMIN_NEW_ORDER":
        return { icon: "receipt-outline", color: "#2563eb", bg: "#dbeafe" };
      case "ADMIN_NEW_USER":
        return { icon: "person-add-outline", color: "#0891b2", bg: "#cffafe" };
      case "ADMIN_SIGNALEMENT":
      case "SIGNALEMENT_ENVOYE":
        return { icon: "flag-outline", color: "#dc2626", bg: "#fee2e2" };
      default:
        return {
          icon: "notifications-outline",
          color: "#6b7280",
          bg: "#f3f4f6",
        };
    }
  };

  const resolveMobileRoute = (link?: string) => {
    const value = String(link || "").trim();

    if (!value) return "/notifications";

    if (value.includes("messages.php")) return "/messages";
    if (value.includes("mes_commandes.php")) return "/mes-commandes";
    if (value.includes("mes_ventes.php")) return "/mes-ventes";
    if (value.includes("mes_annonces.php")) return "/mes-annonces";
    if (value.includes("profil.php")) return "/mon-compte";
    if (value.includes("notifications.php")) return "/notifications";

    const annonceMatch = value.match(/annonce\.php\?id=(\d+)/i);
    if (annonceMatch) return `/annonce/${annonceMatch[1]}`;

    const commandeMatch = value.match(/commande\.php\?id=(\d+)/i);
    if (commandeMatch) return `/commande/${commandeMatch[1]}`;

    const venteMatch = value.match(/vente\.php\?id=(\d+)/i);
    if (venteMatch) return `/vente/${venteMatch[1]}`;

    return "/notifications";
  };

  const goToNotif = async (item: NotificationItem) => {
    const route = item.mobile_route || resolveMobileRoute(item.href);

    if (route.startsWith("/annonce/")) {
      router.push(route as any);
      return;
    }

    if (route.startsWith("/commande/")) {
      router.push(route as any);
      return;
    }

    if (route.startsWith("/vente/")) {
      router.push(route as any);
      return;
    }

    if (route === "/messages") {
      router.push("/messages");
      return;
    }

    if (route === "/mes-commandes") {
      router.push("/mes-commandes");
      return;
    }

    if (route === "/mes-ventes") {
      router.push("/mes-ventes");
      return;
    }

    if (route === "/mes-annonces") {
      router.push("/mes-annonces");
      return;
    }

    if (route === "/mon-compte") {
      router.push("/mon-compte");
      return;
    }

    router.push("/notifications");
  };

  async function loadNotifications() {
    try {
      setLoading(true);
      setErrorMsg("");

      const currentUser = await getUser();
      setUser(currentUser);

      if (!currentUser?.id_user) {
        setErrorMsg("Connexion requise.");
        setItems([]);
        return;
      }

      const res = await fetch(
        `${API_BASE}/notifications_list_mobile.php?user_id=${Number(currentUser.id_user)}`,
        {
          headers: {
            Accept: "application/json",
          },
        },
      );

      const rawText = await res.text();

      if (!rawText || rawText.trim() === "") {
        setErrorMsg("Réponse vide du serveur.");
        setItems([]);
        return;
      }

      let data: any = null;

      try {
        data = JSON.parse(rawText);
      } catch {
        setErrorMsg("Réponse non JSON du serveur.");
        setItems([]);
        return;
      }

      if (!data.ok) {
        setErrorMsg(data.message || "Erreur chargement notifications.");
        setItems([]);
        return;
      }

      setItems(Array.isArray(data.notifications) ? data.notifications : []);
      await refreshNotifications();
    } catch (e: any) {
      setErrorMsg(String(e));
      setItems([]);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadNotifications();
  }, []);

  useFocusEffect(
    useCallback(() => {
      loadNotifications();
    }, []),
  );

  if (loading) {
    return (
      <>
        <Stack.Screen options={{ title: "Notifications" }} />
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#2563eb" />
          <Text style={styles.loadingText}>Chargement...</Text>
        </View>
      </>
    );
  }

  if (!user) {
    return (
      <>
        <Stack.Screen options={{ title: "Notifications" }} />
        <View style={styles.center}>
          <Text style={styles.errorTitle}>Connexion requise</Text>
          <Text style={styles.errorText}>
            Tu dois te connecter pour voir tes notifications.
          </Text>

          <TouchableOpacity
            style={styles.primaryBtn}
            onPress={() => router.push("/login")}
          >
            <Text style={styles.primaryBtnText}>Connexion</Text>
          </TouchableOpacity>
        </View>
      </>
    );
  }

  if (errorMsg) {
    return (
      <>
        <Stack.Screen options={{ title: "Notifications" }} />
        <View style={styles.center}>
          <Text style={styles.errorTitle}>Erreur</Text>
          <Text style={styles.errorText}>{errorMsg}</Text>

          <TouchableOpacity
            style={styles.primaryBtn}
            onPress={loadNotifications}
          >
            <Text style={styles.primaryBtnText}>Réessayer</Text>
          </TouchableOpacity>
        </View>
      </>
    );
  }

  return (
    <>
      <Stack.Screen options={{ title: "Notifications" }} />

      <ScrollView
        style={styles.container}
        contentContainerStyle={styles.content}
      >
        <View style={styles.headerRow}>
          <View>
            <Text style={styles.pageTitle}>Notifications</Text>
            <Text style={styles.pageSubTitle}>
              Toutes tes notifications récentes.
            </Text>
          </View>

          <TouchableOpacity
            style={styles.outlineBtn}
            onPress={() => router.back()}
          >
            <Text style={styles.outlineBtnText}>Retour</Text>
          </TouchableOpacity>
        </View>

        {items.length === 0 ? (
          <View style={styles.emptyCard}>
            <Ionicons
              name="notifications-off-outline"
              size={34}
              color="#9ca3af"
            />
            <Text style={styles.emptyTitle}>Aucune notification</Text>
            <Text style={styles.emptyText}>
              Tu n’as aucune notification pour le moment.
            </Text>
          </View>
        ) : (
          items.map((item) => {
            const cfg = iconConfig(item.type_notification);
            const isUnread = Number(item.is_read || 0) === 0;

            return (
              <TouchableOpacity
                key={item.id_notification}
                activeOpacity={0.86}
                style={[styles.card, isUnread && styles.cardUnread]}
                onPress={() => goToNotif(item)}
              >
                <View style={[styles.iconWrap, { backgroundColor: cfg.bg }]}>
                  <Ionicons
                    name={cfg.icon as any}
                    size={24}
                    color={cfg.color}
                  />
                </View>

                <View style={styles.cardBody}>
                  <View style={styles.titleRow}>
                    <Text style={styles.cardTitle}>
                      {item.titre || "Notification"}
                    </Text>

                    {isUnread ? (
                      <View style={styles.newBadge}>
                        <Text style={styles.newBadgeText}>Nouveau</Text>
                      </View>
                    ) : null}
                  </View>

                  <Text style={styles.cardText}>{item.contenu || ""}</Text>

                  <Text style={styles.cardDate}>
                    {String(item.created_at || "").substring(0, 16)}
                  </Text>
                </View>
              </TouchableOpacity>
            );
          })
        )}
      </ScrollView>
    </>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#f3f4f6",
  },
  content: {
    padding: 14,
    paddingBottom: 30,
  },
  center: {
    flex: 1,
    backgroundColor: "#fff",
    justifyContent: "center",
    alignItems: "center",
    padding: 24,
  },
  loadingText: {
    marginTop: 10,
    fontSize: 16,
    color: "#111827",
  },
  errorTitle: {
    fontSize: 24,
    fontWeight: "800",
    color: "#111827",
    marginBottom: 8,
  },
  errorText: {
    fontSize: 15,
    color: "#6b7280",
    textAlign: "center",
    marginBottom: 16,
  },
  primaryBtn: {
    backgroundColor: "#2563eb",
    paddingHorizontal: 18,
    paddingVertical: 12,
    borderRadius: 12,
  },
  primaryBtnText: {
    color: "#fff",
    fontWeight: "800",
    fontSize: 15,
  },
  headerRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "flex-start",
    gap: 10,
    marginBottom: 16,
  },
  pageTitle: {
    fontSize: 28,
    fontWeight: "900",
    color: "#111827",
  },
  pageSubTitle: {
    marginTop: 4,
    fontSize: 15,
    color: "#6b7280",
  },
  outlineBtn: {
    borderWidth: 1,
    borderColor: "#d1d5db",
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 10,
    backgroundColor: "#fff",
  },
  outlineBtnText: {
    color: "#374151",
    fontWeight: "700",
  },
  emptyCard: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 24,
    alignItems: "center",
  },
  emptyTitle: {
    marginTop: 10,
    fontSize: 18,
    fontWeight: "800",
    color: "#111827",
  },
  emptyText: {
    marginTop: 6,
    fontSize: 14,
    color: "#6b7280",
    textAlign: "center",
  },
  card: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 16,
    marginBottom: 12,
    flexDirection: "row",
    gap: 14,
    alignItems: "flex-start",
  },
  cardUnread: {
    backgroundColor: "#f8fbff",
    borderWidth: 1,
    borderColor: "#dbeafe",
  },
  iconWrap: {
    width: 52,
    height: 52,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  cardBody: {
    flex: 1,
  },
  titleRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    flexWrap: "wrap",
    marginBottom: 6,
  },
  cardTitle: {
    fontSize: 20,
    fontWeight: "900",
    color: "#111827",
  },
  newBadge: {
    backgroundColor: "#ec4899",
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 4,
  },
  newBadgeText: {
    color: "#fff",
    fontSize: 12,
    fontWeight: "800",
  },
  cardText: {
    fontSize: 15,
    color: "#6b7280",
    lineHeight: 22,
  },
  cardDate: {
    marginTop: 10,
    fontSize: 13,
    color: "#9ca3af",
    fontWeight: "600",
  },
});
