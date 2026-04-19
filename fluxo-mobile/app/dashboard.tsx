import React, { useEffect, useMemo, useState } from "react";
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
  Dimensions,
} from "react-native";
import { Stack, router } from "expo-router";
import { getUser } from "../utils/auth";
import { Feather } from "@expo/vector-icons";
import Svg, { Polyline, Line, Circle, Text as SvgText } from "react-native-svg";

const API_BASE = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/api";
const SCREEN_WIDTH = Dimensions.get("window").width;

type ChartItem = {
  date: string;
  label: string;
  montant: number;
};

type TopAnnonce = {
  id_annonce: number;
  titre: string;
  cover_image_url?: string | null;
  qty_vendue: number;
  montant: number;
};

type StatsType = {
  total_ventes: number;
  commandes: number;
  articles_vendus: number;
  nouvelles_ventes: number;
};

export default function DashboardScreen() {
  const [loading, setLoading] = useState(true);
  const [errorMsg, setErrorMsg] = useState("");
  const [user, setUser] = useState<any>(null);
  const [stats, setStats] = useState<StatsType | null>(null);
  const [chart, setChart] = useState<ChartItem[]>([]);
  const [topAnnonces, setTopAnnonces] = useState<TopAnnonce[]>([]);

  useEffect(() => {
    async function loadDashboard() {
      try {
        setLoading(true);
        setErrorMsg("");

        const currentUser = await getUser();
        setUser(currentUser);

        if (!currentUser) {
          setErrorMsg("Connexion requise.");
          return;
        }

        const res = await fetch(
          `${API_BASE}/dashboard.php?user_id=${Number(currentUser.id_user)}`
        );
        const data = await res.json();

        if (!data.ok) {
          setErrorMsg(data.message || "Erreur chargement dashboard");
          return;
        }

        setStats(data.stats || null);
        setChart(data.chart || []);
        setTopAnnonces(data.top_annonces || []);
      } catch (error: any) {
        setErrorMsg(String(error));
      } finally {
        setLoading(false);
      }
    }

    loadDashboard();
  }, []);

  const chartData = useMemo(() => {
    const width = Math.max(SCREEN_WIDTH - 56, 280);
    const height = 220;
    const paddingLeft = 18;
    const paddingRight = 18;
    const paddingTop = 18;
    const paddingBottom = 34;

    const maxValue = Math.max(...chart.map((x) => Number(x.montant || 0)), 100);
    const innerWidth = width - paddingLeft - paddingRight;
    const innerHeight = height - paddingTop - paddingBottom;

    const points = chart.map((item, index) => {
      const x =
        paddingLeft +
        (chart.length <= 1 ? 0 : (index / (chart.length - 1)) * innerWidth);

      const y =
        paddingTop +
        innerHeight -
        (Math.max(Number(item.montant || 0), 0) / maxValue) * innerHeight;

      return { x, y, ...item };
    });

    const polylinePoints = points.map((p) => `${p.x},${p.y}`).join(" ");

    return {
      width,
      height,
      paddingLeft,
      paddingRight,
      paddingTop,
      paddingBottom,
      innerHeight,
      maxValue,
      points,
      polylinePoints,
    };
  }, [chart]);

  function money(x: number) {
    return `${Number(x || 0).toLocaleString("fr-FR", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })} DH`;
  }

  if (loading) {
    return (
      <>
        <Stack.Screen options={{ title: "Dashboard" }} />
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
        <Stack.Screen options={{ title: "Dashboard" }} />
        <View style={styles.center}>
          <Text style={styles.errorTitle}>Connexion requise</Text>
          <Text style={styles.errorText}>
            Tu dois te connecter pour accéder au dashboard.
          </Text>

          <TouchableOpacity
            style={styles.primaryBtn}
            onPress={() => router.push("/login")}
          >
            <Text style={styles.primaryBtnText}>Aller à la connexion</Text>
          </TouchableOpacity>
        </View>
      </>
    );
  }

  if (errorMsg || !stats) {
    return (
      <>
        <Stack.Screen options={{ title: "Dashboard" }} />
        <View style={styles.center}>
          <Text style={styles.errorTitle}>Erreur</Text>
          <Text style={styles.errorText}>{errorMsg || "Erreur inconnue"}</Text>

          <TouchableOpacity
            style={styles.primaryBtn}
            onPress={() => router.back()}
          >
            <Text style={styles.primaryBtnText}>Retour</Text>
          </TouchableOpacity>
        </View>
      </>
    );
  }

  return (
    <>
      <Stack.Screen options={{ title: "Dashboard" }} />

      <ScrollView style={styles.container} contentContainerStyle={styles.content}>
        <View style={styles.headerBox}>
          <View style={{ flex: 1 }}>
            <Text style={styles.pageTitle}>Dashboard vendeur</Text>
            <Text style={styles.pageSub}>
              Statistiques de tes ventes (paiements acceptés).
            </Text>
          </View>

          <View style={styles.topActions}>
            <TouchableOpacity
              style={styles.outlineBtn}
              onPress={() => router.push("/mes-ventes")}
            >
              <Feather name="shopping-bag" size={16} color="#374151" />
              <Text style={styles.outlineBtnText}>Mes ventes</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.outlineBtn}
              onPress={() => router.push("/(tabs)")}
            >
              <Feather name="grid" size={16} color="#374151" />
              <Text style={styles.outlineBtnText}>Annonces</Text>
            </TouchableOpacity>
          </View>
        </View>

        <View style={styles.statsGrid}>
          <View style={styles.statCard}>
            <Text style={styles.statLabel}>Total ventes</Text>
            <Text style={styles.statValue}>{money(stats.total_ventes)}</Text>
          </View>

          <View style={styles.statCard}>
            <Text style={styles.statLabel}>Commandes</Text>
            <Text style={styles.statValue}>{stats.commandes}</Text>
          </View>

          <View style={styles.statCard}>
            <Text style={styles.statLabel}>Articles vendus</Text>
            <Text style={styles.statValue}>{stats.articles_vendus}</Text>
          </View>

          <View style={styles.statCard}>
            <Text style={styles.statLabel}>Nouvelles ventes</Text>
            <Text style={styles.statValue}>{stats.nouvelles_ventes}</Text>
          </View>
        </View>

        <View style={styles.sectionRow}>
          <View style={styles.bigCard}>
            <Text style={styles.sectionTitle}>Ventes (30 derniers jours)</Text>

            <ScrollView horizontal showsHorizontalScrollIndicator={false}>
              <View>
                <Svg
                  width={chartData.width}
                  height={chartData.height}
                  style={{ backgroundColor: "#fff" }}
                >
                  <Line
                    x1={chartData.paddingLeft}
                    y1={chartData.height - chartData.paddingBottom}
                    x2={chartData.width - chartData.paddingRight}
                    y2={chartData.height - chartData.paddingBottom}
                    stroke="#d1d5db"
                    strokeWidth="1"
                  />

                  <Line
                    x1={chartData.paddingLeft}
                    y1={chartData.paddingTop}
                    x2={chartData.paddingLeft}
                    y2={chartData.height - chartData.paddingBottom}
                    stroke="#d1d5db"
                    strokeWidth="1"
                  />

                  {[0, 0.25, 0.5, 0.75, 1].map((ratio, index) => {
                    const y =
                      chartData.paddingTop +
                      chartData.innerHeight -
                      ratio * chartData.innerHeight;
                    const value = chartData.maxValue * ratio;

                    return (
                      <React.Fragment key={index}>
                        <Line
                          x1={chartData.paddingLeft}
                          y1={y}
                          x2={chartData.width - chartData.paddingRight}
                          y2={y}
                          stroke="#eef2f7"
                          strokeWidth="1"
                        />
                        <SvgText
                          x={chartData.paddingLeft}
                          y={y - 4}
                          fontSize="10"
                          fill="#9ca3af"
                        >
                          {Math.round(value).toString()}
                        </SvgText>
                      </React.Fragment>
                    );
                  })}

                  {chartData.points.length > 1 ? (
                    <Polyline
                      points={chartData.polylinePoints}
                      fill="none"
                      stroke="#60a5fa"
                      strokeWidth="3"
                    />
                  ) : null}

                  {chartData.points.map((p, index) => (
                    <React.Fragment key={index}>
                      <Circle cx={p.x} cy={p.y} r="3.5" fill="#2563eb" />
                    </React.Fragment>
                  ))}

                  {chartData.points
                    .filter(
                      (_, index) =>
                        index % 6 === 0 || index === chartData.points.length - 1
                    )
                    .map((p, index) => (
                      <SvgText
                        key={`label-${index}`}
                        x={p.x - 12}
                        y={chartData.height - 10}
                        fontSize="10"
                        fill="#6b7280"
                      >
                        {p.label}
                      </SvgText>
                    ))}
                </Svg>
              </View>
            </ScrollView>

            <Text style={styles.chartNote}>
              Graphique basé sur les paiements acceptés.
            </Text>
          </View>

          <View style={styles.bigCard}>
            <Text style={styles.sectionTitle}>Top annonces</Text>

            {topAnnonces.length > 0 ? (
              topAnnonces.map((item) => (
                <View key={item.id_annonce} style={styles.topItem}>
                  <View style={{ flex: 1, paddingRight: 10 }}>
                    <Text style={styles.topItemTitle} numberOfLines={2}>
                      {item.titre}
                    </Text>
                    <Text style={styles.topItemSub}>
                      Qté vendue: {item.qty_vendue}
                    </Text>
                  </View>

                  <View style={{ alignItems: "flex-end" }}>
                    <Text style={styles.topItemAmount}>{money(item.montant)}</Text>

                    <TouchableOpacity
                      onPress={() => router.push(`/annonce/${item.id_annonce}`)}
                    >
                      <Text style={styles.topItemLink}>Voir</Text>
                    </TouchableOpacity>
                  </View>
                </View>
              ))
            ) : (
              <Text style={styles.emptyText}>Aucune vente pour le moment.</Text>
            )}
          </View>
        </View>
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
  headerBox: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 16,
    marginBottom: 14,
  },
  pageTitle: {
    fontSize: 22,
    fontWeight: "900",
    color: "#111827",
  },
  pageSub: {
    fontSize: 14,
    color: "#6b7280",
    marginTop: 4,
  },
  topActions: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 10,
    marginTop: 14,
  },
  outlineBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
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
  statsGrid: {
    gap: 12,
    marginBottom: 14,
  },
  statCard: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 16,
  },
  statLabel: {
    color: "#6b7280",
    fontSize: 14,
    marginBottom: 8,
  },
  statValue: {
    fontSize: 22,
    fontWeight: "900",
    color: "#111827",
  },
  sectionRow: {
    gap: 14,
  },
  bigCard: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 16,
  },
  sectionTitle: {
    fontSize: 20,
    fontWeight: "900",
    color: "#111827",
    marginBottom: 12,
  },
  chartNote: {
    marginTop: 8,
    fontSize: 13,
    color: "#6b7280",
  },
  topItem: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "flex-start",
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: "#f1f5f9",
  },
  topItemTitle: {
    fontSize: 16,
    fontWeight: "800",
    color: "#111827",
  },
  topItemSub: {
    marginTop: 5,
    fontSize: 14,
    color: "#6b7280",
  },
  topItemAmount: {
    fontSize: 16,
    fontWeight: "900",
    color: "#111827",
  },
  topItemLink: {
    marginTop: 6,
    color: "#2563eb",
    fontWeight: "700",
  },
  emptyText: {
    color: "#6b7280",
    fontSize: 15,
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
});