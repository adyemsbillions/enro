export type User = {
  id: number;
  name: string;
  email: string;
  phone: string;
  coins: number;
  cash: number;
  referral_code: string;
  created_at: string;
};

export type Me = {
  user: User;
  streak: { streak: number; checked_today: boolean };
  tasks_today: number;
  daily_task_limit: number;
  unread_notifications: number;
  stats: { tasks_done: number; special_done: number; total_cashout: number };
};

/** Adsterra Smartlink only: shown full-screen after every `every` task claims, and opened by "Watch ad" tasks. */
export type AdsConfig = {
  interstitial: { enabled: boolean; every: number };
  smartlink: string;
};

export type AppConfig = {
  app_name: string;
  coins_per_naira: number;
  airtime_amounts: number[];
  min_withdraw: number;
  withdraw_fee: number;
  daily_task_limit: number;
  ref_bonus_referrer: number;
  ref_bonus_new: number;
  ref_unlock_tasks: number;
  streak_bonus: number;
  support_email: string;
  push: { server: boolean; evening_hour: number; evening_enabled: boolean };
  ads: AdsConfig;
};

export type TaskType = 'watch' | 'visit' | 'social' | 'daily';

export type Task = {
  id: number;
  title: string;
  description: string | null;
  type: TaskType;
  reward: number;
  duration: number;
  daily_cap: number;
  done_today: number;
  available: boolean;
};

export type SpecialTask = {
  id: number;
  title: string;
  description: string | null;
  steps: string[];
  url: string | null;
  reward: number;
  proof_type: 'screenshot' | 'screenshot_text' | 'text';
  slots: number;
  taken: number;
  eta: string | null;
  expires_at: string | null;
  my_status: 'pending' | 'approved' | 'rejected' | null;
  reject_reason: string | null;
};

export type Txn = {
  id: number;
  currency: 'coin' | 'cash';
  amount: number;
  kind: string;
  description: string;
  status: 'success' | 'pending' | 'failed' | 'rejected';
  created_at: string;
};

export type Notice = {
  id: number | string; // 'b12' = admin broadcast
  title: string;
  body: string;
  icon: string;
  read_at: string | null;
  created_at: string;
  screen: string | null;
  ref_id: number | null;
};

export type WalletOptions = {
  networks: string[];
  coins_per_naira: number;
  airtime: { amount: number; coins: number }[];
  data_plans: Record<string, { id: number; name: string; validity: string; coins: number }[]>;
  min_withdraw: number;
  withdraw_fee: number;
  banks: { name: string; code: string }[];
};
