export type ApiEnvelope<T> = {
  success: boolean;
  message: string;
  data: T;
  errors?: Record<string, string[]> | null;
  meta?: {
    pagination?: Pagination;
    [key: string]: unknown;
  } | null;
};

export type Pagination = {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
};

export type AdminUser = {
  id: number;
  name: string;
  email: string;
  phone?: string | null;
  roles: string[];
  permissions?: string[];
};

export type DashboardSummary = {
  kpis: {
    sales_today: number;
    orders_today: number;
    pending_payment: number;
    orders_to_ship: number;
    low_stock_items: number;
    customers: number;
    active_products: number;
    payment_failed_orders_today?: number;
    failed_payments_today?: number;
    delivery_failed_open?: number;
    open_returns?: number;
    failed_jobs?: number;
  };
  definitions?: Record<string, string>;
  sales_last_7_days: Array<{
    day: string;
    total: number;
    orders: number;
  }>;
};

export type AdminOrderListItem = {
  id: number;
  order_number: string;
  status: string;
  grand_total: number;
  payment_method?: string | null;
  payment_status?: string | null;
  customer: {
    id: number | null;
    name?: string | null;
    email?: string | null;
  };
  placed_at?: string | null;
};

export type AdminOrderDetail = {
  id: number;
  order_number: string;
  status: string;
  currency?: string;
  subtotal: number;
  discount_total: number;
  tax_total: number;
  shipping_total: number;
  grand_total: number;
  coupon_code?: string | null;
  payment_method?: string | null;
  customer: {
    id: number | null;
    name?: string | null;
    email?: string | null;
    phone?: string | null;
  };
  shipping_address?: Record<string, unknown> | null;
  billing_address?: Record<string, unknown> | null;
  payment?: {
    id: number;
    status: string;
    method: string;
    amount: number;
    paid_at?: string | null;
    provider?: string | null;
    provider_payment_id?: string | null;
    provider_order_id?: string | null;
    upi_mode?: string | null;
    channel?: string | null;
  } | null;
  shipment?: {
    status?: string | null;
    carrier?: string | null;
    tracking_number?: string | null;
    tracking_url?: string | null;
    shipped_at?: string | null;
  } | null;
  items: Array<{
    id: number;
    product_id: number;
    name: string;
    sku: string;
    unit_price: number;
    quantity: number;
    line_total: number;
  }>;
  status_history: Array<{
    from?: string | null;
    to: string;
    note?: string | null;
    at?: string | null;
  }>;
  created_at?: string | null;
};

export type AdminProductListItem = {
  id: number;
  name: string;
  sku: string;
  slug: string;
  product_type: string;
  price: number;
  compare_at_price?: number | null;
  status: string;
  stock_qty?: number;
  brand?: string | null;
  categories: string[];
  thumbnail_url?: string | null;
  updated_at?: string | null;
};

export type AdminProductDetail = AdminProductListItem & {
  description?: string | null;
  brand_id?: number | null;
  category_ids: number[];
  tags: string[];
  is_featured?: boolean;
  is_new?: boolean;
  stock_status?: string | null;
  plant?: Record<string, unknown> | null;
  images: Array<{
    id: number;
    url: string;
    alt?: string | null;
    is_primary: boolean;
    sort_order: number;
  }>;
  inventory: Array<{
    id: number;
    warehouse_id: number;
    warehouse_code?: string | null;
    qty_on_hand: number;
    qty_reserved: number;
    qty_damaged: number;
    sellable: number;
    low_stock_threshold: number;
  }>;
};

export type AdminCategory = {
  id: number;
  parent_id?: number | null;
  name: string;
  slug: string;
  image_url?: string | null;
  sort_order: number;
  status: string;
};

export type InventoryRow = {
  id: number;
  warehouse_id: number;
  warehouse_code?: string | null;
  product_id: number;
  product_name?: string | null;
  sku?: string | null;
  sellable: number;
  qty_on_hand: number;
  qty_reserved: number;
  qty_damaged?: number;
  low_stock_threshold: number;
  is_low_stock: boolean;
  thumbnail_url?: string | null;
  categories?: string[];
  stock_status?: "IN_STOCK" | "LOW_STOCK" | "OUT_OF_STOCK" | string;
  updated_at?: string | null;
};
