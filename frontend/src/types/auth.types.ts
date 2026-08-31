export interface Utilisateur {
  id: number; uuid: string; etablissement_id: number | null; role_id: number;
  nom: string; prenom: string; email: string; telephone: string | null;
  photo_url: string | null; genre: string | null; date_naissance: string | null;
  est_actif: boolean; email_verifie: boolean; derniere_connexion: string | null;
  cree_le: string; mis_a_jour_le: string;
  role: { id: number; code: string; nom: string; est_systeme: boolean };
  etablissement: { id: number; uuid: string; nom: string; ville: string; pays: string } | null;
}
export interface Session {
  access_token: string; token_type: "bearer"; expires_in: number;
  utilisateur: Utilisateur; permissions: string[];
}
export interface ApiResponse<T = unknown> { succes: boolean; message: string; donnees: T; }
export interface PaginatedResponse<T> {
  data: T[]; current_page: number; last_page: number; per_page: number; total: number;
  next_page_url: string | null; prev_page_url: string | null;
}
