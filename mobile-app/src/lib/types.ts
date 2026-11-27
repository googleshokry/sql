export type Subscription = {
  id: number;
  plan: string | null;
  plan_color: string | null;
  trainer: string | null;
  start_date: string;
  end_date: string;
  days_left: number;
  total_days: number;
  status: 'active' | 'upcoming' | 'frozen' | 'expired' | 'cancelled';
  status_label: string;
  sessions_total: number | null;
  sessions_used: number;
  sessions_left: number | null;
  freeze_days_allowed: number;
  freeze_days_used: number;
  frozen_from: string | null;
  frozen_until: string | null;
  guest_invitations: number;
  guest_invitations_used: number;
  total: number;
  paid: number;
  remaining: number;
};

export type Member = {
  id: number;
  code: string;
  name: string;
  phone: string;
  email: string | null;
  gender: 'male' | 'female';
  birth_date: string | null;
  age: number | null;
  photo_url: string | null;
  height: number | null;
  weight: number | null;
  trainer: { name: string; phone: string | null } | null;
  joined_at: string | null;
  is_blocked: boolean;
  locale: string;
  is_inside: boolean;
  balance: number;
  subscription: Subscription | null;
};

export type StaffUser = {
  id: number;
  name: string;
  email: string;
  role: string;
  role_label: string;
  is_manager: boolean;
};

export type Attendance = {
  id: number;
  member?: { id: number; name: string; code: string; photo_url: string | null };
  check_in_at: string;
  check_out_at: string | null;
  duration: string | null;
  is_guest: boolean;
  guest_name: string | null;
};

export type Payment = {
  id: number;
  receipt_number: string;
  amount: number;
  method: string;
  method_label: string;
  type: string;
  type_label: string;
  plan: string | null;
  paid_at: string;
};

export type Measurement = {
  id: number;
  measured_at: string;
  weight: number | null;
  height: number | null;
  bmi: number | null;
  body_fat: number | null;
  muscle_mass: number | null;
  chest: number | null;
  waist: number | null;
  hips: number | null;
  arms: number | null;
  thighs: number | null;
  notes: string | null;
};

export type Gym = {
  name: string;
  phone: string | null;
  address: string | null;
  about: string | null;
  working_hours: string | null;
  location_url: string | null;
  instagram: string | null;
  facebook: string | null;
  logo_url: string | null;
  currency: string;
};

export type Plan = {
  id: number;
  name: string;
  duration_days: number;
  price: number;
  sessions_count: number | null;
  freeze_days: number;
  guest_invitations: number;
  includes_trainer: boolean;
  color: string | null;
  description: string | null;
};

export type Announcement = { id: number; title: string; body: string | null; image_url: string | null; published_at: string };

export type HomeData = {
  member: Member;
  stats: {
    visits_total: number;
    visits_this_month: number;
    visits_last_7_days: { date: string; count: number }[];
    last_visit: string | null;
  };
};

export type CheckInResult = {
  ok: boolean;
  action: 'in' | 'out' | null;
  message: string;
  can_force?: boolean;
  member: Member | null;
};

export type Paginated<T> = { data: T[]; meta?: { current_page: number; last_page: number } };
