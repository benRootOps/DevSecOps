// src/router/index.tsx
import { lazy, Suspense } from "react";
import { createBrowserRouter, Navigate } from "react-router-dom";
import { AppShell } from "../components/layout/AppShell";

// Lazy loading de toutes les pages
const LoginPage           = lazy(() => import("../pages/auth/LoginPage"));
const InscriptionPage     = lazy(() => import("../pages/auth/InscriptionEtablissementPage"));
const DashboardPage       = lazy(() => import("../pages/dashboard/DashboardPage"));
const UtilisateursPage    = lazy(() => import("../pages/utilisateurs/UtilisateursPage"));
const DemandesPage        = lazy(() => import("../pages/demandes/DemandesPage"));
const EmploiDuTempsPage   = lazy(() => import("../pages/emploiDuTemps/EmploiDuTempsPage"));
const PresencesPage       = lazy(() => import("../pages/presences/PresencesPage"));
const NotesPage           = lazy(() => import("../pages/notes/NotesPage"));
const DeliberationsPage   = lazy(() => import("../pages/deliberations/DeliberationsPage"));
const BulletinsPage       = lazy(() => import("../pages/deliberations/BulletinsPage"));
const FinancierPage       = lazy(() => import("../pages/financier/FinancierPage"));
const AbonnementsPage     = lazy(() => import("../pages/abonnements/AbonnementsPage"));
const EnseignantsPage     = lazy(() => import("../pages/utilisateurs/EnseignantsPage"));
const EtudiantsPage       = lazy(() => import("../pages/utilisateurs/EtudiantsPage"));
const EtablissementsPage  = lazy(() => import("../pages/etablissements/EtablissementsPage"));
const ProfilPage          = lazy(() => import("../pages/utilisateurs/ProfilPage"));

// Loader global entre pages
function PageLoader() {
  return (
    <div className="flex-1 flex items-center justify-center h-full">
      <div className="flex flex-col items-center gap-3">
        <div className="w-8 h-8 rounded-full border-2 border-[#7C6AF7] border-t-transparent animate-spin" />
        <span className="text-xs text-[#5C6785]">Chargement…</span>
      </div>
    </div>
  );
}

function S({ children }: { children: React.ReactNode }) {
  return <Suspense fallback={<PageLoader />}>{children}</Suspense>;
}

const router = createBrowserRouter([
  // Login (public)
  {
    path: "/login",
    element: <S><LoginPage /></S>,
  },
  {
    path: "/inscription",
    element: <S><InscriptionPage /></S>,
  },

  // App protégée
  {
    path: "/",
    element: <AppShell />,
    children: [
      { index: true, element: <Navigate to="/dashboard" replace /> },

      { path: "dashboard",      element: <S><DashboardPage /></S> },
      { path: "utilisateurs",   element: <S><UtilisateursPage /></S> },
      { path: "enseignants",    element: <S><EnseignantsPage /></S> },
      { path: "etudiants",      element: <S><EtudiantsPage /></S> },
      { path: "etablissements", element: <S><EtablissementsPage /></S> },
      { path: "demandes",       element: <S><DemandesPage /></S> },
      { path: "emploi-du-temps",element: <S><EmploiDuTempsPage /></S> },
      { path: "presences",      element: <S><PresencesPage /></S> },
      { path: "notes",          element: <S><NotesPage /></S> },
      { path: "deliberations",  element: <S><DeliberationsPage /></S> },
      { path: "bulletins",      element: <S><BulletinsPage /></S> },
      { path: "financier",      element: <S><FinancierPage /></S> },
      { path: "abonnements",    element: <S><AbonnementsPage /></S> },
      { path: "profil",         element: <S><ProfilPage /></S> },

      // Catch-all
      { path: "*", element: <Navigate to="/dashboard" replace /> },
    ],
  },
]);

export default router;
