import React, { useEffect, useRef, useState } from "react";
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  FlatList,
  TextInput,
  KeyboardAvoidingView,
  Platform,
  ActivityIndicator,
  Dimensions,
  Alert,
  SafeAreaView,
} from "react-native";
import { Stack, router, useLocalSearchParams } from "expo-router";
import { getCurrentUserId } from "../utils/auth";

const API_BASE = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/api";
const { width } = Dimensions.get("window");
const IS_DESKTOP = width >= 900;

type ConversationType = {
  id_annonce: number;
  other_id: number;
  other_nom: string;
  annonce_titre: string;
  annonce_statut: string;
  mode_vente: string;
  last_date: string;
  last_contenu: string;
  unread_count: number;
};

type MessageType = {
  id_message: number;
  sender: "me" | "other";
  contenu: string;
  date_envoi: string;
};

type ThreadConversationType = {
  other_nom: string;
  annonce_titre: string;
  can_chat: boolean;
};

export default function MessagesScreen() {
  const params = useLocalSearchParams();

  const paramAnnonceId = String(params.annonceId ?? "");
  const paramToId = String(params.to ?? "");
  const paramVendeur = String(params.vendeur ?? "");
  const paramTitre = String(params.titre ?? "");

  const openedFromAnnonce = !!paramAnnonceId && !!paramToId;

  const [currentUserId, setCurrentUserId] = useState<number | null>(null);

  const [loadingUser, setLoadingUser] = useState(true);
  const [loadingConvs, setLoadingConvs] = useState(true);
  const [loadingThread, setLoadingThread] = useState(false);
  const [sending, setSending] = useState(false);

  const [errorMsg, setErrorMsg] = useState("");
  const [input, setInput] = useState("");

  const [totalUnread, setTotalUnread] = useState(0);
  const [conversations, setConversations] = useState<ConversationType[]>([]);
  const [messages, setMessages] = useState<MessageType[]>([]);

  const [selectedAnnonceId, setSelectedAnnonceId] = useState<string>(paramAnnonceId);
  const [selectedToId, setSelectedToId] = useState<string>(paramToId);
  const [headerName, setHeaderName] = useState(paramVendeur || "Vendeur");
  const [headerTitle, setHeaderTitle] = useState(paramTitre || "Annonce");
  const [canChat, setCanChat] = useState(true);

  const [showConversationListMobile, setShowConversationListMobile] = useState(!openedFromAnnonce);

  const flatListRef = useRef<FlatList>(null);

  useEffect(() => {
    async function loadUser() {
      try {
        setLoadingUser(true);
        const uid = await getCurrentUserId();
        setCurrentUserId(uid);
      } finally {
        setLoadingUser(false);
      }
    }

    loadUser();
  }, []);

  useEffect(() => {
    if (!currentUserId) return;

    if (selectedToId && Number(selectedToId) === currentUserId) {
      setErrorMsg("Tu ne peux pas t’envoyer un message à toi-même.");
      setLoadingConvs(false);
      return;
    }

    loadConversations();
  }, [currentUserId]);

  useEffect(() => {
    if (!currentUserId) return;
    if (!selectedAnnonceId || !selectedToId) return;
    if (Number(selectedToId) === currentUserId) return;

    loadThread(selectedAnnonceId, selectedToId);
  }, [currentUserId, selectedAnnonceId, selectedToId]);

  async function loadConversations() {
    try {
      setLoadingConvs(true);
      setErrorMsg("");

      const res = await fetch(
        `${API_BASE}/messages_list.php?user_id=${currentUserId}`
      );
      const data = await res.json();

      if (!data.ok) {
        setErrorMsg(data.message || "Erreur chargement conversations");
        return;
      }

      const convs = data.conversations || [];
      setConversations(convs);
      setTotalUnread(Number(data.total_unread || 0));

      if (!selectedAnnonceId || !selectedToId) {
        if (convs.length > 0) {
          setSelectedAnnonceId(String(convs[0].id_annonce));
          setSelectedToId(String(convs[0].other_id));
          setHeaderName(convs[0].other_nom || "Vendeur");
          setHeaderTitle(convs[0].annonce_titre || "Annonce");
        }
      }
    } catch (error: any) {
      setErrorMsg(String(error));
    } finally {
      setLoadingConvs(false);
    }
  }

  async function loadThread(annonceId: string, toId: string) {
    try {
      setLoadingThread(true);
      setErrorMsg("");

      const res = await fetch(
        `${API_BASE}/messages_thread.php?user_id=${currentUserId}&annonce=${annonceId}&to=${toId}`
      );
      const data = await res.json();

      if (!data.ok) {
        setErrorMsg(data.message || "Erreur chargement messages");
        return;
      }

      setMessages(data.messages || []);

      const conv: ThreadConversationType | undefined = data.conversation;
      setHeaderName(conv?.other_nom || "Vendeur");
      setHeaderTitle(conv?.annonce_titre || "Annonce");
      setCanChat(Boolean(conv?.can_chat));

      setTimeout(() => {
        flatListRef.current?.scrollToEnd({ animated: true });
      }, 250);

      await loadConversations();
    } catch (error: any) {
      setErrorMsg(String(error));
    } finally {
      setLoadingThread(false);
    }
  }

  async function sendMessage() {
    const text = input.trim();
    if (!text || sending || !canChat || !currentUserId) return;
    if (!selectedAnnonceId || !selectedToId) return;

    if (Number(selectedToId) === currentUserId) {
      Alert.alert("Impossible", "Tu ne peux pas t’envoyer un message à toi-même.");
      return;
    }

    try {
      setSending(true);
      setErrorMsg("");

      const res = await fetch(`${API_BASE}/messages_send.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          user_id: currentUserId,
          id_annonce: Number(selectedAnnonceId),
          to: Number(selectedToId),
          contenu: text,
        }),
      });

      const data = await res.json();

      if (!data.ok) {
        setErrorMsg(data.message || "Erreur envoi");
        return;
      }

      setInput("");
      await loadThread(selectedAnnonceId, selectedToId);

      setTimeout(() => {
        flatListRef.current?.scrollToEnd({ animated: true });
      }, 250);
    } catch (error: any) {
      setErrorMsg(String(error));
    } finally {
      setSending(false);
    }
  }

  function selectConversation(conv: ConversationType) {
    setSelectedAnnonceId(String(conv.id_annonce));
    setSelectedToId(String(conv.other_id));
    setHeaderName(conv.other_nom || "Vendeur");
    setHeaderTitle(conv.annonce_titre || "Annonce");

    if (!IS_DESKTOP) {
      setShowConversationListMobile(false);
    }
  }

  function formatTime(dateStr: string) {
    if (!dateStr) return "";
    const d = new Date(dateStr.replace(" ", "T"));
    return d.toLocaleTimeString([], {
      hour: "2-digit",
      minute: "2-digit",
    });
  }

  function formatShortDate(dateStr: string) {
    if (!dateStr) return "";
    return dateStr.substring(0, 16);
  }

  function goBackInsideMessages() {
    if (openedFromAnnonce) {
      router.back();
      return;
    }

    if (!IS_DESKTOP && !showConversationListMobile) {
      setShowConversationListMobile(true);
      return;
    }

    router.back();
  }

  function openAnnonce() {
    if (!selectedAnnonceId) return;
    router.push(`/annonce/${selectedAnnonceId}`);
  }

  function openSellerProfile() {
    if (!selectedToId) return;
    router.push(`/vendeur/${selectedToId}`);
  }

  if (loadingUser) {
    return (
      <>
        <Stack.Screen options={{ title: "Messages" }} />
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#2563eb" />
          <Text style={styles.loadingText}>Chargement...</Text>
        </View>
      </>
    );
  }

  if (!currentUserId) {
    return (
      <>
        <Stack.Screen options={{ title: "Messages" }} />
        <View style={styles.center}>
          <Text style={styles.errorTitle}>Connexion requise</Text>
          <Text style={styles.errorText}>
            Tu dois te connecter avant d’accéder aux messages.
          </Text>

          <TouchableOpacity
            style={styles.loginBtn}
            onPress={() => router.push("/login")}
          >
            <Text style={styles.loginBtnText}>Aller à la connexion</Text>
          </TouchableOpacity>
        </View>
      </>
    );
  }

  if (loadingConvs) {
    return (
      <>
        <Stack.Screen options={{ title: "Messages" }} />
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#2563eb" />
          <Text style={styles.loadingText}>Chargement des conversations...</Text>
        </View>
      </>
    );
  }

  if (errorMsg && !selectedAnnonceId && !selectedToId) {
    return (
      <>
        <Stack.Screen options={{ title: "Messages" }} />
        <View style={styles.center}>
          <Text style={styles.errorTitle}>Erreur</Text>
          <Text style={styles.errorText}>{errorMsg}</Text>

          <TouchableOpacity style={styles.loginBtn} onPress={() => router.back()}>
            <Text style={styles.loginBtnText}>Retour</Text>
          </TouchableOpacity>
        </View>
      </>
    );
  }

  const showList = !openedFromAnnonce && (IS_DESKTOP || showConversationListMobile);
  const showThread = openedFromAnnonce || IS_DESKTOP || !showConversationListMobile;

  return (
    <>
      <Stack.Screen options={{ title: "Messages" }} />

      <SafeAreaView style={styles.safe}>
        <KeyboardAvoidingView
          style={styles.container}
          behavior={Platform.OS === "ios" ? "padding" : "height"}
          keyboardVerticalOffset={Platform.OS === "ios" ? 88 : 20}
        >
          <View
            style={[
              styles.wrapper,
              { flexDirection: openedFromAnnonce ? "column" : IS_DESKTOP ? "row" : "column" },
            ]}
          >
            {showList && (
              <View style={styles.convPanel}>
                <View style={styles.convHeader}>
                  <Text style={styles.convHeaderTitle}>Messages</Text>
                  <Text style={styles.convHeaderSub}>Non lus : {totalUnread}</Text>
                </View>

                {conversations.length === 0 ? (
                  <View style={styles.emptyConvBox}>
                    <Text style={styles.emptyConvText}>Aucune conversation.</Text>
                  </View>
                ) : (
                  <FlatList
                    data={conversations}
                    keyExtractor={(item) => `${item.id_annonce}-${item.other_id}`}
                    renderItem={({ item }) => {
                      const active =
                        String(item.id_annonce) === selectedAnnonceId &&
                        String(item.other_id) === selectedToId;

                      return (
                        <TouchableOpacity
                          style={[
                            styles.convItem,
                            active && styles.convItemActive,
                          ]}
                          onPress={() => selectConversation(item)}
                        >
                          <View style={styles.convTopRow}>
                            <Text
                              style={[
                                styles.convName,
                                active && styles.convNameActive,
                              ]}
                              numberOfLines={1}
                            >
                              {item.other_nom}
                            </Text>

                            {item.unread_count > 0 ? (
                              <View style={styles.badge}>
                                <Text style={styles.badgeText}>
                                  {item.unread_count}
                                </Text>
                              </View>
                            ) : null}
                          </View>

                          <Text
                            style={[
                              styles.convAnnonce,
                              active && styles.convAnnonceActive,
                            ]}
                            numberOfLines={1}
                          >
                            {item.annonce_titre}
                          </Text>

                          <Text
                            style={[
                              styles.convLast,
                              active && styles.convLastActive,
                            ]}
                            numberOfLines={1}
                          >
                            {item.last_contenu}
                          </Text>

                          <Text
                            style={[
                              styles.convDate,
                              active && styles.convDateActive,
                            ]}
                          >
                            {formatShortDate(item.last_date)}
                          </Text>
                        </TouchableOpacity>
                      );
                    }}
                  />
                )}
              </View>
            )}

            {showThread && (
              <View style={styles.threadPanel}>
                {!selectedAnnonceId || !selectedToId ? (
                  <View style={styles.center}>
                    <Text style={styles.emptyThreadTitle}>Choisis une conversation</Text>
                    <Text style={styles.emptyThreadText}>
                      Ouvre une discussion depuis une annonce ou sélectionne une conversation.
                    </Text>
                  </View>
                ) : (
                  <>
                    <View style={styles.headerBox}>
                      <TouchableOpacity onPress={goBackInsideMessages}>
                        <Text style={styles.backText}>← Retour</Text>
                      </TouchableOpacity>

                      <TouchableOpacity onPress={openSellerProfile}>
                        <Text style={styles.headerTitle}>{headerName}</Text>
                      </TouchableOpacity>

                      <Text style={styles.headerSub}>Annonce : {headerTitle}</Text>
                      <Text style={styles.headerSub}>ID : {selectedAnnonceId}</Text>

                      <View style={styles.topButtonsRow}>
                        <TouchableOpacity style={styles.topActionBtn} onPress={openAnnonce}>
                          <Text style={styles.topActionBtnText}>Voir annonce</Text>
                        </TouchableOpacity>

                        <TouchableOpacity style={styles.topActionOutlineBtn} onPress={openSellerProfile}>
                          <Text style={styles.topActionOutlineBtnText}>Voir profil vendeur</Text>
                        </TouchableOpacity>
                      </View>
                    </View>

                    {loadingThread ? (
                      <View style={styles.center}>
                        <ActivityIndicator size="large" color="#2563eb" />
                        <Text style={styles.loadingText}>Chargement du fil...</Text>
                      </View>
                    ) : Number(selectedToId) === currentUserId ? (
                      <View style={styles.center}>
                        <Text style={styles.errorTitle}>Impossible</Text>
                        <Text style={styles.errorText}>
                          Tu ne peux pas t’envoyer un message à toi-même.
                        </Text>
                      </View>
                    ) : errorMsg ? (
                      <View style={styles.center}>
                        <Text style={styles.errorTitle}>Erreur</Text>
                        <Text style={styles.errorText}>{errorMsg}</Text>
                      </View>
                    ) : (
                      <>
                        <FlatList
                          ref={flatListRef}
                          data={messages}
                          keyExtractor={(item) => String(item.id_message)}
                          contentContainerStyle={styles.list}
                          keyboardShouldPersistTaps="handled"
                          onContentSizeChange={() =>
                            flatListRef.current?.scrollToEnd({ animated: true })
                          }
                          renderItem={({ item }) => (
                            <View
                              style={[
                                styles.messageRow,
                                item.sender === "me"
                                  ? styles.rowMe
                                  : styles.rowOther,
                              ]}
                            >
                              <View
                                style={[
                                  styles.bubble,
                                  item.sender === "me"
                                    ? styles.bubbleMe
                                    : styles.bubbleOther,
                                ]}
                              >
                                <Text
                                  style={[
                                    styles.messageText,
                                    item.sender === "me"
                                      ? styles.textMe
                                      : styles.textOther,
                                  ]}
                                >
                                  {item.contenu}
                                </Text>
                                <Text
                                  style={[
                                    styles.timeText,
                                    item.sender === "me"
                                      ? styles.timeMe
                                      : styles.timeOther,
                                  ]}
                                >
                                  {formatTime(item.date_envoi)}
                                </Text>
                              </View>
                            </View>
                          )}
                          ListEmptyComponent={
                            <View style={styles.emptyWrap}>
                              <Text style={styles.emptyText}>
                                Aucun message. Écris le premier.
                              </Text>
                            </View>
                          }
                        />

                        {!canChat ? (
                          <View style={styles.disabledBar}>
                            <Text style={styles.disabledBarText}>
                              Cette annonce n’autorise pas la discussion.
                            </Text>
                          </View>
                        ) : (
                          <View style={styles.inputWrap}>
                            <TextInput
                              value={input}
                              onChangeText={setInput}
                              placeholder="Écrire un message..."
                              placeholderTextColor="#9ca3af"
                              style={styles.input}
                              multiline
                              textAlignVertical="top"
                            />

                            <TouchableOpacity
                              style={[
                                styles.sendBtn,
                                sending && styles.sendBtnDisabled,
                              ]}
                              onPress={sendMessage}
                              disabled={sending}
                            >
                              <Text style={styles.sendBtnText}>
                                {sending ? "..." : "Envoyer"}
                              </Text>
                            </TouchableOpacity>
                          </View>
                        )}
                      </>
                    )}
                  </>
                )}
              </View>
            )}
          </View>
        </KeyboardAvoidingView>
      </SafeAreaView>
    </>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: "#f3f4f6",
  },
  container: {
    flex: 1,
    backgroundColor: "#f3f4f6",
  },
  wrapper: {
    flex: 1,
  },
  convPanel: {
    width: IS_DESKTOP ? 340 : "100%",
    backgroundColor: "#ffffff",
    borderRightWidth: IS_DESKTOP ? 1 : 0,
    borderRightColor: "#e5e7eb",
    flex: IS_DESKTOP ? 0 : 1,
  },
  threadPanel: {
    flex: 1,
    backgroundColor: "#f3f4f6",
  },
  convHeader: {
    paddingTop: 18,
    paddingHorizontal: 16,
    paddingBottom: 14,
    borderBottomWidth: 1,
    borderBottomColor: "#e5e7eb",
    backgroundColor: "#fff",
  },
  convHeaderTitle: {
    fontSize: 24,
    fontWeight: "800",
    color: "#111827",
  },
  convHeaderSub: {
    marginTop: 4,
    fontSize: 14,
    color: "#6b7280",
  },
  convItem: {
    paddingHorizontal: 14,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: "#f1f5f9",
    backgroundColor: "#fff",
  },
  convItemActive: {
    backgroundColor: "#2563eb",
  },
  convTopRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  convName: {
    fontSize: 16,
    fontWeight: "700",
    color: "#111827",
    flex: 1,
    marginRight: 8,
  },
  convNameActive: {
    color: "#fff",
  },
  convAnnonce: {
    marginTop: 4,
    fontSize: 13,
    color: "#6b7280",
  },
  convAnnonceActive: {
    color: "#dbeafe",
  },
  convLast: {
    marginTop: 4,
    fontSize: 13,
    color: "#4b5563",
  },
  convLastActive: {
    color: "#eff6ff",
  },
  convDate: {
    marginTop: 6,
    fontSize: 12,
    color: "#9ca3af",
  },
  convDateActive: {
    color: "#dbeafe",
  },
  badge: {
    minWidth: 22,
    height: 22,
    paddingHorizontal: 6,
    borderRadius: 11,
    backgroundColor: "#ef4444",
    alignItems: "center",
    justifyContent: "center",
  },
  badgeText: {
    color: "#fff",
    fontSize: 12,
    fontWeight: "700",
  },
  headerBox: {
    backgroundColor: "#fff",
    paddingTop: 18,
    paddingHorizontal: 16,
    paddingBottom: 14,
    borderBottomWidth: 1,
    borderBottomColor: "#e5e7eb",
  },
  backText: {
    color: "#2563eb",
    fontWeight: "700",
    marginBottom: 10,
    fontSize: 15,
  },
  headerTitle: {
    fontSize: 22,
    fontWeight: "800",
    color: "#111827",
  },
  headerSub: {
    marginTop: 4,
    color: "#6b7280",
    fontSize: 14,
  },
  topButtonsRow: {
    flexDirection: "row",
    gap: 10,
    marginTop: 14,
    flexWrap: "wrap",
  },
  topActionBtn: {
    backgroundColor: "#2563eb",
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 12,
  },
  topActionBtnText: {
    color: "#fff",
    fontSize: 14,
    fontWeight: "700",
  },
  topActionOutlineBtn: {
    borderWidth: 1,
    borderColor: "#2563eb",
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 12,
    backgroundColor: "#fff",
  },
  topActionOutlineBtnText: {
    color: "#2563eb",
    fontSize: 14,
    fontWeight: "700",
  },
  center: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
    padding: 24,
    backgroundColor: "#fff",
  },
  loadingText: {
    marginTop: 10,
    fontSize: 16,
    color: "#111827",
  },
  errorTitle: {
    fontSize: 22,
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
  loginBtn: {
    backgroundColor: "#2563eb",
    paddingHorizontal: 18,
    paddingVertical: 14,
    borderRadius: 12,
  },
  loginBtnText: {
    color: "#fff",
    fontWeight: "700",
    fontSize: 15,
  },
  list: {
    padding: 14,
    paddingBottom: 30,
    flexGrow: 1,
  },
  emptyWrap: {
    paddingTop: 40,
    alignItems: "center",
  },
  emptyText: {
    color: "#6b7280",
    fontSize: 15,
  },
  emptyConvBox: {
    padding: 20,
  },
  emptyConvText: {
    color: "#6b7280",
    fontSize: 15,
  },
  emptyThreadTitle: {
    fontSize: 22,
    fontWeight: "800",
    color: "#111827",
    marginBottom: 8,
  },
  emptyThreadText: {
    fontSize: 15,
    color: "#6b7280",
    textAlign: "center",
  },
  messageRow: {
    marginBottom: 12,
    flexDirection: "row",
  },
  rowMe: {
    justifyContent: "flex-end",
  },
  rowOther: {
    justifyContent: "flex-start",
  },
  bubble: {
    maxWidth: "78%",
    borderRadius: 16,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  bubbleMe: {
    backgroundColor: "#2563eb",
    borderBottomRightRadius: 6,
  },
  bubbleOther: {
    backgroundColor: "#fff",
    borderBottomLeftRadius: 6,
  },
  messageText: {
    fontSize: 15,
    lineHeight: 21,
  },
  textMe: {
    color: "#fff",
  },
  textOther: {
    color: "#111827",
  },
  timeText: {
    fontSize: 11,
    marginTop: 6,
  },
  timeMe: {
    color: "#dbeafe",
    textAlign: "right",
  },
  timeOther: {
    color: "#6b7280",
    textAlign: "left",
  },
  disabledBar: {
    backgroundColor: "#fef3c7",
    borderTopWidth: 1,
    borderTopColor: "#f3e8a3",
    paddingHorizontal: 16,
    paddingVertical: 14,
  },
  disabledBarText: {
    color: "#92400e",
    fontSize: 14,
    fontWeight: "600",
    textAlign: "center",
  },
  inputWrap: {
    flexDirection: "row",
    alignItems: "flex-end",
    gap: 10,
    paddingHorizontal: 12,
    paddingTop: 10,
    paddingBottom: Platform.OS === "ios" ? 16 : 12,
    backgroundColor: "#fff",
    borderTopWidth: 1,
    borderTopColor: "#e5e7eb",
  },
  input: {
    flex: 1,
    minHeight: 54,
    maxHeight: 120,
    borderWidth: 1,
    borderColor: "#d1d5db",
    borderRadius: 16,
    paddingHorizontal: 14,
    paddingTop: 14,
    paddingBottom: 14,
    fontSize: 15,
    color: "#111827",
    backgroundColor: "#fff",
  },
  sendBtn: {
    backgroundColor: "#2563eb",
    paddingHorizontal: 18,
    paddingVertical: 16,
    borderRadius: 14,
    minWidth: 96,
    alignItems: "center",
    justifyContent: "center",
  },
  sendBtnDisabled: {
    opacity: 0.6,
  },
  sendBtnText: {
    color: "#fff",
    fontWeight: "700",
    fontSize: 16,
  },
});