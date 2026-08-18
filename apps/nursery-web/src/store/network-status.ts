"use client";

import { create } from "zustand";
import type { ClassifiedNetworkError, NetworkKind } from "@/lib/network-errors";
import { bannerCopy } from "@/lib/network-errors";

type NetworkState = {
  kind: NetworkKind;
  message: string | null;
  showBanner: boolean;
  reportSuccess: () => void;
  reportFailure: (error: ClassifiedNetworkError) => void;
  setBrowserOffline: (offline: boolean) => void;
  setReconnecting: () => void;
};

function computeShow(kind: NetworkKind) {
  return (
    kind === "offline" ||
    kind === "apiUnavailable" ||
    kind === "apiTimeout" ||
    kind === "serverError"
  );
}

export const useNetworkStatusStore = create<NetworkState>((set, get) => ({
  kind: "online",
  message: null,
  showBanner: false,

  reportSuccess: () =>
    set({ kind: "online", message: null, showBanner: false }),

  reportFailure: (error) => {
    if (
      error.kind === "authExpired" ||
      (error.kind === "unknownError" &&
        error.statusCode != null &&
        error.statusCode < 500 &&
        error.statusCode !== 408 &&
        error.statusCode !== 429)
    ) {
      return;
    }
    const kind =
      error.kind === "unknownError" ? "apiUnavailable" : error.kind;
    set({
      kind,
      message: error.userMessage,
      showBanner: computeShow(kind),
    });
  },

  setBrowserOffline: (offline) => {
    if (offline) {
      set({
        kind: "offline",
        message: "You're offline. Check your internet connection.",
        showBanner: true,
      });
      return;
    }
    if (get().kind === "offline") {
      set({
        kind: "reconnecting",
        message: bannerCopy("reconnecting"),
        showBanner: false,
      });
    }
  },

  setReconnecting: () =>
    set({
      kind: "reconnecting",
      message: bannerCopy("reconnecting"),
      showBanner: false,
    }),
}));
