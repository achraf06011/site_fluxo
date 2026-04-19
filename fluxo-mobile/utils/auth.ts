import AsyncStorage from "@react-native-async-storage/async-storage";

export const USER_KEY = "fluxo_user";
export const PENDING_VERIFICATION_KEY = "fluxo_pending_verification";

export async function saveUser(user: any) {
  await AsyncStorage.setItem(USER_KEY, JSON.stringify(user));
}

export async function getUser() {
  const data = await AsyncStorage.getItem(USER_KEY);
  return data ? JSON.parse(data) : null;
}

export async function getCurrentUserId() {
  const user = await getUser();
  return user?.id_user ?? null;
}

export async function savePendingVerification(data: {
  id_user?: number | null;
  email?: string;
}) {
  await AsyncStorage.setItem(
    PENDING_VERIFICATION_KEY,
    JSON.stringify({
      id_user: data?.id_user ?? null,
      email: data?.email ?? "",
    })
  );
}

export async function getPendingVerification() {
  const data = await AsyncStorage.getItem(PENDING_VERIFICATION_KEY);
  return data ? JSON.parse(data) : null;
}

export async function clearPendingVerification() {
  await AsyncStorage.removeItem(PENDING_VERIFICATION_KEY);
}

export async function logoutUser() {
  await AsyncStorage.multiRemove([
    USER_KEY,
    PENDING_VERIFICATION_KEY,
  ]);
}

export async function isLoggedIn() {
  const user = await getUser();
  return !!user?.id_user;
}