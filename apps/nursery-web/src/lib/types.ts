export type ApiEnvelope<T> = {
  success: boolean;
  message: string;
  data: T;
  errors: Record<string, string[]> | null;
  meta: {
    request_id?: string;
    timestamp?: string;
    pagination?: Pagination;
    error_code?: string;
  };
};

export type Pagination = {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
};

export type User = {
  id: number;
  name: string;
  email: string;
  phone?: string | null;
  roles?: string[];
};

export type ProductSummary = {
  id: number;
  sku: string;
  name: string;
  slug: string;
  product_type: string;
  price: number;
  compare_at_price?: number | null;
  currency: string;
  stock_status: string;
  thumbnail_url?: string | null;
  rating_avg?: number;
  rating_count?: number;
  badges?: string[];
};

export type PlantProfile = {
  common_name?: string;
  scientific_name?: string | null;
  indoor_outdoor?: string | null;
  sunlight?: string | null;
  water_requirement?: string | null;
  soil_type?: string | null;
  difficulty_level?: string | null;
  care_level?: string | null;
  pet_safety?: string | null;
  growing_instructions?: string | null;
  temperature_min_c?: number | null;
  temperature_max_c?: number | null;
};

export type ProductDetail = ProductSummary & {
  description?: string | null;
  available_qty?: number;
  brand?: { id: number; name: string; slug: string } | null;
  categories?: { id: number; name: string; slug: string }[];
  tags?: string[];
  images?: { id: number; url: string; alt?: string; is_primary?: boolean }[];
  plant?: PlantProfile | null;
  related?: ProductSummary[];
  active_campaigns?: Array<{ id: number; slug: string; title: string }>;
  subscription_enabled?: boolean;
  subscription_plans?: Array<{
    id: number;
    name: string;
    slug: string;
    frequency: string;
    quantity_default: number;
    unit_price: number;
    compare_at_price?: number | null;
    currency: string;
    description?: string | null;
    billing_model?: string;
  }>;
};

export type Banner = {
  id: number;
  title: string;
  image_url: string;
  link_type?: string | null;
  link_value?: string | null;
  sort_order?: number;
};

export type CampaignSummary = {
  id: number;
  slug: string;
  title: string;
  subtitle?: string | null;
  short_description?: string | null;
  image_url?: string | null;
  banner_image?: string | null;
  thumbnail_image?: string | null;
  season_code?: string | null;
  type?: string | null;
  starts_at?: string | null;
  ends_at?: string | null;
  status?: string;
  is_featured?: boolean;
  priority?: number;
};

export type OfferProduct = ProductSummary & {
  original_price?: number | null;
  discount_amount?: number | null;
  discount_percentage?: number | null;
};

export type OffersFeed = {
  featured_campaigns: CampaignSummary[];
  upcoming_campaigns: CampaignSummary[];
  sale_products: OfferProduct[];
  public_coupons?: Array<{
    code: string;
    name?: string;
    discount_type: string;
    discount_value: number;
    min_order_amount?: number | null;
    ends_at?: string | null;
  }>;
};

export type PlantMatchResult = {
  product: ProductSummary;
  match_score: number;
  match_reasons: string[];
};

export type PlantFinderMatch = {
  criteria: Record<string, unknown>;
  results: PlantMatchResult[];
  approximate?: boolean;
};

export type HomeData = {
  banners: Banner[];
  campaigns: CampaignSummary[];
  featured_products: ProductSummary[];
  new_arrivals?: ProductSummary[];
  best_sellers?: ProductSummary[];
  recommended_for_you?: ProductSummary[];
  indoor_plants?: ProductSummary[];
  outdoor_plants?: ProductSummary[];
  low_maintenance?: ProductSummary[];
  categories?: Category[];
  definitions?: Record<string, string>;
};

export type Category = {
  id: number;
  name: string;
  slug: string;
  image_url?: string | null;
  parent_id?: number | null;
  description?: string | null;
  children?: Category[];
};

export type CartItem = {
  id: number;
  product_id: number;
  product_variant_id?: number | null;
  name: string;
  sku?: string;
  unit_price: number;
  quantity: number;
  line_total: number;
  thumbnail_url?: string | null;
  slug?: string;
};

export type FreeDelivery = {
  enabled: boolean;
  threshold: number;
  remaining: number;
  qualifies: boolean;
};

export type Cart = {
  id?: number | null;
  cart_token?: string | null;
  currency: string;
  items: CartItem[];
  item_count: number;
  subtotal: number;
  discount_total: number;
  coupon_code?: string | null;
  tax_total: number;
  shipping_total: number;
  grand_total: number;
  free_delivery?: FreeDelivery | null;
  warnings?: Array<{
    code: string;
    severity: string;
    message: string;
    product_id?: number;
    blocking?: boolean;
  }>;
  checkout_blocked?: boolean;
};

export type Address = {
  id: number;
  label?: string | null;
  name: string;
  phone: string;
  line1: string;
  line2?: string | null;
  city: string;
  state: string;
  postal_code: string;
  country: string;
  is_default?: boolean;
};

export type ShippingMethod = {
  id: number;
  code: string;
  name: string;
  price: number;
  currency?: string;
};

export type OrderSummary = {
  id: number;
  order_number: string;
  status: string;
  payment_status?: string | null;
  payment_method?: string | null;
  grand_total: number;
  currency?: string;
  item_count?: number;
  thumbnail?: string | null;
  preview_name?: string | null;
  estimated_delivery?: string | null;
  can_cancel?: boolean;
  can_reorder?: boolean;
  can_return?: boolean;
  placed_at?: string | null;
  created_at?: string | null;
};

export type OrderTrackingStep = {
  status: string;
  title: string;
  description?: string;
  completed: boolean;
  current?: boolean;
  created_at?: string | null;
};

export type OrderDetail = {
  id: number;
  order_number: string;
  status: string;
  currency: string;
  subtotal: number;
  discount_total: number;
  tax_total: number;
  shipping_total: number;
  grand_total: number;
  coupon_code?: string | null;
  payment_method?: string | null;
  cancel_reason?: string | null;
  cancelled_at?: string | null;
  can_cancel?: boolean;
  can_reorder?: boolean;
  can_return?: boolean;
  actions?: { can_cancel?: boolean; can_reorder?: boolean; can_return?: boolean };
  return_reasons?: Array<{ code: string; label: string }>;
  returnable_items?: Array<{
    order_item_id: number;
    product_id?: number | null;
    name?: string | null;
    purchased_qty?: number;
    already_returned_qty?: number;
    returnable_qty: number;
  }>;
  returns?: Array<{
    id: number;
    order_id: number;
    status: string;
    notes?: string | null;
    created_at?: string | null;
    pickup_tracking?: {
      carrier?: string | null;
      tracking_number?: string | null;
      tracking_url?: string | null;
      status?: string | null;
    } | null;
    timeline?: Array<{ status?: string; at?: string; note?: string | null }>;
    items?: Array<{ order_item_id: number; quantity: number; reason?: string | null }>;
  }>;
  refunds?: Array<{
    id: number;
    amount: number;
    currency?: string;
    status: string;
    reason?: string | null;
    created_at?: string | null;
  }>;
  reviewable_product_ids?: number[];
  payment?: {
    status?: string | null;
    method?: string | null;
    amount?: number;
    paid_at?: string | null;
  } | null;
  shipping_address?: {
    name?: string;
    phone?: string;
    line1?: string;
    line2?: string | null;
    city?: string;
    state?: string;
    postal_code?: string;
    country?: string;
  } | null;
  shipment?: {
    status?: string;
    carrier?: string | null;
    tracking_number?: string | null;
    tracking_url?: string | null;
    estimated_delivery?: string | null;
  } | null;
  tracking?: {
    current_status?: string;
    estimated_delivery?: string | null;
    timeline?: OrderTrackingStep[];
    events?: Array<{
      status: string;
      description?: string | null;
      location?: string | null;
      event_at?: string | null;
    }>;
    shipment?: {
      carrier?: string | null;
      tracking_number?: string | null;
      tracking_url?: string | null;
      status?: string | null;
    } | null;
  } | null;
  status_history?: Array<{ status: string; at?: string | null; note?: string | null }>;
  items: Array<{
    id: number;
    product_id?: number;
    product_slug?: string | null;
    name: string;
    sku?: string;
    unit_price: number;
    quantity: number;
    line_total: number;
    thumbnail_url?: string | null;
  }>;
  placed_at?: string | null;
  created_at?: string | null;
};

export type ReviewSummary = {
  rating_avg?: number;
  rating_count?: number;
  distribution?: Record<string, number>;
};

export type WishlistItem = {
  id: number;
  product_id: number;
  product?: ProductSummary;
  name?: string;
  slug?: string;
  price?: number;
  thumbnail_url?: string | null;
};
