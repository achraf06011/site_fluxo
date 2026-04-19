import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import { useRouter } from "expo-router";
import { getUser } from "../utils/auth";

const API_BASE = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/api";

type PopupNotification = {
  id_notification: number;
  type_notification: string;
  titre: string;
  contenu: string;
  lien: string;
  mobile_route: string;
  is_read: number;
  is_popup_seen: number;
  created_at: string;
  icon: string;
  icon_color: string;
  icon_bg: string;
};

type NotificationsContextType = {
  badgeCount: number;
  popup: PopupNotification | null;
  popupVisible: boolean;
  closePopup: () => Promise<void>;
  openPopup: () => Promise<void>;
  refreshNotifications: () => Promise<void>;
};

const NotificationsContext = createContext<NotificationsContextType>({
  badgeCount: 0,
  popup: null,
  popupVisible: false,
  closePopup: async () => {},
  openPopup: async () => {},
  refreshNotifications: async () => {},
});

export function useNotifications() {
  return useContext(NotificationsContext);
}

export function NotificationsProvider({
  children,
}: {
  children: React.ReactNode;
}) {
  const router = useRouter();

  const [badgeCount, setBadgeCount] = useState(0);
  const [popup, setPopup] = useState<PopupNotification | null>(null);
  const [popupVisible, setPopupVisible] = useState(false);
  const [userId, setUserId] = useState<number>(0);

  const shownPopupIdRef = useRef<number>(0);

  const markPopupSeen = useCallback(
    async (id: number) => {
      if (!userId || !id) return;

      try {
        await fetch(`${API_BASE}/notifications_seen_mobile.php`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
          },
          body: JSON.stringify({
            user_id: Number(userId),
            id_notification: Number(id),
          }),
        });
      } catch {}
    },
    [userId]
  );

  const refreshNotifications = useCallback(async () => {
    try {
      const currentUser = await getUser();
      const uid = Number(currentUser?.id_user || 0);

      setUserId(uid);

      if (!uid) {
        setBadgeCount(0);
        setPopup(null);
        setPopupVisible(false);
        shownPopupIdRef.current = 0;
        return;
      }

      const res = await fetch(
        `${API_BASE}/notifications_poll_mobile.php?user_id=${uid}`,
        {
          headers: {
            Accept: "application/json",
          },
        }
      );

      const rawText = await res.text();
      if (!rawText || rawText.trim() === "") return;

      let data: any = null;

      try {
        data = JSON.parse(rawText);
      } catch {
        return;
      }

      if (!data.ok) return;

      setBadgeCount(Number(data.badge || 0));

      if (
        data.popup &&
        Number(data.popup.id_notification || 0) > 0 &&
        Number(data.popup.id_notification) !== shownPopupIdRef.current
      ) {
        shownPopupIdRef.current = Number(data.popup.id_notification);
        setPopup(data.popup);
        setPopupVisible(true);
      }
    } catch {}
  }, []);

  useEffect(() => {
    refreshNotifications();

    const interval = setInterval(() => {
      refreshNotifications();
    }, 5000);

    return () => clearInterval(interval);
  }, [refreshNotifications]);

  const closePopup = useCallback(async () => {
    if (popup?.id_notification) {
      await markPopupSeen(Number(popup.id_notification));
    }

    setPopupVisible(false);
    setPopup(null);
    await refreshNotifications();
  }, [popup, markPopupSeen, refreshNotifications]);

  const openPopup = useCallback(async () => {
    if (!popup) return;

    const route = popup.mobile_route || "/notifications";
    const notifId = Number(popup.id_notification || 0);

    if (notifId > 0) {
      await markPopupSeen(notifId);
    }

    setPopupVisible(false);
    setPopup(null);

    if (route === "/messages") {
      router.push("/messages");
    } else if (route === "/mes-commandes") {
      router.push("/mes-commandes");
    } else if (route === "/mes-ventes") {
      router.push("/mes-ventes");
    } else if (route === "/mes-annonces") {
      router.push("/mes-annonces");
    } else if (route === "/mon-compte") {
      router.push("/mon-compte");
    } else if (route.startsWith("/annonce/")) {
      router.push(route as any);
    } else if (route.startsWith("/commande/")) {
      router.push(route as any);
    } else if (route.startsWith("/vente/")) {
      router.push(route as any);
    } else {
      router.push("/notifications");
    }

    await refreshNotifications();
  }, [popup, markPopupSeen, router, refreshNotifications]);

  const value = useMemo(
    () => ({
      badgeCount,
      popup,
      popupVisible,
      closePopup,
      openPopup,
      refreshNotifications,
    }),
    [badgeCount, popup, popupVisible, closePopup, openPopup, refreshNotifications]
  );

  return (
    <NotificationsContext.Provider value={value}>
      {children}
    </NotificationsContext.Provider>
  );
}