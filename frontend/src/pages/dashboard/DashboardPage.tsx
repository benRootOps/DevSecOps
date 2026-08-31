// src/pages/dashboard/DashboardPage.tsx
import { useQuery } from "@tanstack/react-query";
import { Users, GraduationCap, CheckSquare, ClipboardList, Calendar, Building2, CreditCard, BookOpen } from "lucide-react";
import { useAuthStore } from "../../stores/authStore";
import { StatCard, Card, CardHeader, CardBody } from "../../components/ui/components";
import { Badge, Skeleton } from "../../components/ui/atoms";
import client from "../../api/client";

export default function DashboardPage() {
  const { user } = useAuthStore();
  // Super Admin = opérateur de la plateforme (établissements, plans, abonnements
  // globaux). Tous les autres rôles = gestion interne à LEUR université — jamais
  // les mêmes données, cf. séparation multi-tenant.
  const estSuperAdmin = user?.role?.code === "super_admin";
  return estSuperAdmin ? <DashboardPlateforme /> : <DashboardUniversite />;
}

function Entete({ sousTitre }: { sousTitre: string }) {
  const { user } = useAuthStore();
  const heure = new Date().getHours();
  const salut = heure < 12 ? "Bonjour" : "Bonsoir";
  return (
    <div className="flex items-start justify-between">
      <div>
        <h1 className="text-2xl font-bold text-[#F0F2FF]">{salut}, <span className="text-[#7C6AF7]">{user?.prenom}</span> 👋</h1>
        <p className="text-sm text-[#5C6785] mt-1">{sousTitre}</p>
      </div>
      <div className="hidden md:flex items-center gap-2 px-4 py-2 bg-[rgba(124,106,247,0.08)] border border-[rgba(124,106,247,0.2)] rounded-xl">
        <Calendar size={14} className="text-[#7C6AF7]" />
        <span className="text-xs text-[#8A97B5]">{new Date().toLocaleDateString("fr-FR", { weekday: "long", day: "numeric", month: "long", year: "numeric" })}</span>
      </div>
    </div>
  );
}

/* ══════════════════════════════════════════════════════════════
   Vue Super Admin — pilotage de la plateforme
   ══════════════════════════════════════════════════════════════ */
function DashboardPlateforme() {
  const { data: demandes } = useQuery({
    queryKey: ["demandes-etablissement-attente"],
    queryFn: () => client.get("/demandes", { params: { type_demande: "etablissement", statut: "en_attente", par_page: 5 } }).then(r => r.data.donnees),
  });

  const { data: etablissements } = useQuery({
    queryKey: ["etablissements-stats"],
    queryFn: () => client.get("/etablissements/statistiques").then(r => r.data.donnees),
  });

  const { data: abonnements } = useQuery({
    queryKey: ["abonnements-plateforme"],
    queryFn: () => client.get("/abonnements").then(r => r.data.donnees),
  });

  const abonnementsList: any[] = abonnements?.data ?? abonnements ?? [];
  const actifsCount = abonnementsList.filter((a: any) => a.statut === "actif").length;

  return (
    <div className="space-y-6">
      <Entete sousTitre="Vue d'ensemble de la plateforme Univora." />

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard label="Établissements" value={etablissements?.total ?? "—"} icon={<Building2 size={18} />} color="#7C6AF7" />
        <StatCard label="Établissements actifs" value={etablissements?.actifs ?? "—"} icon={<GraduationCap size={18} />} color="#06B6D4" />
        <StatCard label="Demandes en attente" value={demandes?.total ?? "—"} icon={<ClipboardList size={18} />} color="#F97316" />
        <StatCard label="Abonnements actifs" value={actifsCount} icon={<CreditCard size={18} />} color="#10B981" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="lg:col-span-2">
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <h2 className="text-sm font-semibold text-[#F0F2FF]">Demandes d'établissement en attente</h2>
                <a href="/demandes" className="text-xs text-[#7C6AF7] hover:text-[#8B7CFA]">Voir tout →</a>
              </div>
            </CardHeader>
            <CardBody className="p-0">
              {!demandes
                ? <div className="p-6 space-y-3">{[1, 2, 3].map(i => <div key={i} className="flex items-center gap-3"><Skeleton className="w-9 h-9 rounded-full" /><div className="flex-1 space-y-1.5"><Skeleton className="h-3 w-32" /><Skeleton className="h-2.5 w-20" /></div></div>)}</div>
                : demandes?.data?.length === 0
                ? <div className="p-8 text-center text-sm text-[#5C6785]">Aucune demande en attente 🎉</div>
                : <ul>{demandes?.data?.map((d: any, i: number) => (
                    <li key={d.id} className="flex items-center justify-between px-6 py-4 hover:bg-[rgba(255,255,255,0.02)]" style={{ borderTop: i > 0 ? "1px solid rgba(255,255,255,0.04)" : undefined }}>
                      <div className="flex items-center gap-3">
                        <div className="w-9 h-9 rounded-full bg-[rgba(249,115,22,0.1)] flex items-center justify-center text-[#F97316] text-xs font-bold">{d.donnees?.nom?.[0] ?? "?"}</div>
                        <div><div className="text-sm font-medium text-[#F0F2FF]">{d.donnees?.nom ?? "—"}</div><div className="text-xs text-[#5C6785]">{d.donnees?.ville} · {new Date(d.soumis_le).toLocaleDateString("fr-FR")}</div></div>
                      </div>
                      <Badge color="#F97316">En attente</Badge>
                    </li>
                  ))}</ul>}
            </CardBody>
          </Card>
        </div>
        <div className="space-y-3">
          <h2 className="text-sm font-semibold text-[#F0F2FF]">Accès rapides</h2>
          {[
            { label: "Valider une demande", color: "#F97316", icon: ClipboardList, href: "/demandes" },
            { label: "Gérer les plans", color: "#6366F1", icon: CreditCard, href: "/abonnements" },
          ].map(q => (
            <a key={q.href} href={q.href} className="flex items-center gap-3 p-4 bg-[#111420] border border-[rgba(255,255,255,0.06)] rounded-xl hover:border-[rgba(124,106,247,0.3)] transition-all group">
              <div className="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style={{ background: `${q.color}15`, color: q.color }}><q.icon size={17} /></div>
              <span className="text-sm font-medium text-[#8A97B5] group-hover:text-[#F0F2FF] transition-colors">{q.label}</span>
            </a>
          ))}
        </div>
      </div>
    </div>
  );
}

/* ══════════════════════════════════════════════════════════════
   Vue Admin Universitaire (et autres rôles internes) — gestion de
   SA propre université uniquement (scope backend via etablissement_id).
   ══════════════════════════════════════════════════════════════ */
function DashboardUniversite() {
  const { can } = useAuthStore();

  const { data: enseignants } = useQuery({
    queryKey: ["enseignants-count"],
    queryFn: () => client.get("/enseignants").then(r => r.data.donnees),
    enabled: can("enseignants.voir"),
  });

  const { data: etudiants } = useQuery({
    queryKey: ["etudiants-count"],
    queryFn: () => client.get("/etudiants").then(r => r.data.donnees),
    enabled: can("etudiants.voir"),
  });

  const { data: demandes } = useQuery({
    queryKey: ["demandes-internes-attente"],
    queryFn: () => client.get("/demandes", { params: { statut: "en_attente", par_page: 5 } }).then(r => r.data.donnees),
    enabled: can("demandes.voir"),
  });

  const nbEnseignants = enseignants?.total ?? (Array.isArray(enseignants) ? enseignants.length : undefined);
  const nbEtudiants = etudiants?.total ?? (Array.isArray(etudiants) ? etudiants.length : undefined);

  return (
    <div className="space-y-6">
      <Entete sousTitre="Voici un aperçu de votre université." />

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard label="Enseignants" value={nbEnseignants ?? "—"} icon={<GraduationCap size={18} />} color="#7C6AF7" />
        <StatCard label="Étudiants" value={nbEtudiants ?? "—"} icon={<Users size={18} />} color="#06B6D4" />
        <StatCard label="Demandes en attente" value={demandes?.total ?? "—"} icon={<ClipboardList size={18} />} color="#F97316" />
        <StatCard label="Taux de présence" value="—" icon={<CheckSquare size={18} />} color="#10B981" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {can("demandes.voir") && (
          <div className="lg:col-span-2">
            <Card>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <h2 className="text-sm font-semibold text-[#F0F2FF]">Demandes en attente</h2>
                  <a href="/demandes" className="text-xs text-[#7C6AF7] hover:text-[#8B7CFA]">Voir tout →</a>
                </div>
              </CardHeader>
              <CardBody className="p-0">
                {!demandes
                  ? <div className="p-6 space-y-3">{[1, 2, 3].map(i => <div key={i} className="flex items-center gap-3"><Skeleton className="w-9 h-9 rounded-full" /><div className="flex-1 space-y-1.5"><Skeleton className="h-3 w-32" /><Skeleton className="h-2.5 w-20" /></div></div>)}</div>
                  : demandes?.data?.length === 0
                  ? <div className="p-8 text-center text-sm text-[#5C6785]">Aucune demande en attente 🎉</div>
                  : <ul>{demandes?.data?.map((d: any, i: number) => (
                      <li key={d.id} className="flex items-center justify-between px-6 py-4 hover:bg-[rgba(255,255,255,0.02)]" style={{ borderTop: i > 0 ? "1px solid rgba(255,255,255,0.04)" : undefined }}>
                        <div className="flex items-center gap-3">
                          <div className="w-9 h-9 rounded-full bg-[rgba(249,115,22,0.1)] flex items-center justify-center text-[#F97316] text-xs font-bold">{d.donnees?.nom?.[0] ?? "?"}</div>
                          <div><div className="text-sm font-medium text-[#F0F2FF]">{d.donnees?.nom ?? d.donnees?.prenom ?? "—"}</div><div className="text-xs text-[#5C6785]">{d.type_demande} · {new Date(d.soumis_le).toLocaleDateString("fr-FR")}</div></div>
                        </div>
                        <Badge color="#F97316">En attente</Badge>
                      </li>
                    ))}</ul>}
              </CardBody>
            </Card>
          </div>
        )}
        <div className="space-y-3">
          <h2 className="text-sm font-semibold text-[#F0F2FF]">Accès rapides</h2>
          {[
            { label: "Voir les enseignants", color: "#7C6AF7", icon: GraduationCap, href: "/enseignants", perm: "enseignants.voir" },
            { label: "Voir les étudiants", color: "#06B6D4", icon: Users, href: "/etudiants", perm: "etudiants.voir" },
            { label: "Saisir des présences", color: "#10B981", icon: CheckSquare, href: "/presences", perm: "presences.saisir" },
            { label: "Saisir des notes", color: "#A78BFA", icon: BookOpen, href: "/notes", perm: "notes.saisir" },
            { label: "Enregistrer un versement", color: "#F59E0B", icon: ClipboardList, href: "/financier", perm: "financier.saisir" },
          ].filter(q => !q.perm || can(q.perm)).map(q => (
            <a key={q.href} href={q.href} className="flex items-center gap-3 p-4 bg-[#111420] border border-[rgba(255,255,255,0.06)] rounded-xl hover:border-[rgba(124,106,247,0.3)] transition-all group">
              <div className="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style={{ background: `${q.color}15`, color: q.color }}><q.icon size={17} /></div>
              <span className="text-sm font-medium text-[#8A97B5] group-hover:text-[#F0F2FF] transition-colors">{q.label}</span>
            </a>
          ))}
        </div>
      </div>
    </div>
  );
}
