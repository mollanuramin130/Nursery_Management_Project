import { apiGet, apiSend } from "@/lib/api";
import type { Cart, ProductSummary } from "@/lib/types";

export const cartService = {
  get: () => apiGet<Cart>("/cart"),
  addItem: (product_id: number, quantity = 1, product_variant_id?: number) =>
    apiSend<Cart>("post", "/cart/items", { product_id, quantity, product_variant_id }),
  updateItem: (itemId: number, quantity: number) =>
    apiSend<Cart>("put", `/cart/items/${itemId}`, { quantity }),
  removeItem: (itemId: number) => apiSend<Cart>("delete", `/cart/items/${itemId}`),
  clear: () => apiSend<Cart>("delete", "/cart"),
  applyCoupon: (code: string) => apiSend<Cart>("post", "/cart/apply-coupon", { code }),
  removeCoupon: () => apiSend<Cart>("delete", "/cart/coupon"),
  moveToWishlist: (itemId: number) =>
    apiSend<Cart>("post", `/cart/items/${itemId}/move-to-wishlist`),
};

export const catalogService = {
  search: (params: Record<string, unknown>) =>
    apiGet<ProductSummary[] | { products?: ProductSummary[]; items?: ProductSummary[] }>(
      "/search",
      params,
    ),
  related: (productId: number) => apiGet<ProductSummary[]>(`/products/${productId}/related`),
  recommendations: (productId: number, type = "similar") =>
    apiGet<ProductSummary[]>(`/products/${productId}/recommendations`, { type }),
  plantCare: (idOrSlug: string) =>
    apiGet<Record<string, unknown>>(`/plants/${idOrSlug}/care`),
};

export const checkoutService = {
  preview: (body: {
    address_id: number;
    shipping_method_id: number;
    coupon_code?: string;
  }) =>
    apiSend<{
      subtotal: number;
      shipping_total: number;
      discount_total: number;
      tax_total?: number;
      grand_total: number;
    }>("post", "/checkout/preview", body),
  placeOrder: (
    body: {
      address_id: number;
      shipping_method_id: number;
      payment_method: string;
      coupon_code?: string;
      notes?: string;
    },
    requestId?: string,
  ) =>
    apiSend<{ id: number; order_number: string; status: string; grand_total?: number }>(
      "post",
      "/orders",
      body,
      requestId ? { "X-Request-Id": requestId } : undefined,
    ),
};

export const customerService = {
  addresses: () => apiGet<import("@/lib/types").Address[]>("/customer/addresses"),
  createAddress: (body: Record<string, unknown>) =>
    apiSend<import("@/lib/types").Address>("post", "/customer/addresses", body),
  updateAddress: (id: number, body: Record<string, unknown>) =>
    apiSend<import("@/lib/types").Address>("put", `/customer/addresses/${id}`, body),
  deleteAddress: (id: number) => apiSend("delete", `/customer/addresses/${id}`),
  updateProfile: (body: { name?: string; phone?: string | null }) =>
    apiSend<import("@/lib/types").User>("put", "/customer/profile", body),
};
