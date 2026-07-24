from django.urls import path, include
from rest_framework.routers import DefaultRouter

from . import views
from .views import admin_views

# Router pour les ViewSets
router = DefaultRouter()
router.register(r'niveaux', views.NiveauViewSet, basename='niveau')
router.register(r'filieres', views.FiliereViewSet, basename='filiere')
router.register(r'specialites', views.SpecialiteViewSet, basename='specialite')
router.register(r'matieres', views.MatiereViewSet, basename='matiere')
router.register(r'etudiants', views.EtudiantViewSet, basename='etudiant')
router.register(r'notes', views.NoteViewSet, basename='note')

urlpatterns = [
    # ── Authentification ──────────────────────────────────────────────────────
    path('auth/register/enseignant/', views.register_enseignant, name='register_enseignant'),
    path('auth/register/administrateur/', views.register_administrateur, name='register_administrateur'),
    path('auth/login/', views.login, name='login'),
    path('auth/logout/', views.logout, name='logout'),

    path('form-data/', views.get_form_data, name='get_form_data'),
    path('matieres/filter/', views.get_matieres_filtered, name='matieres_filtered'),

    # ── OTP ───────────────────────────────────────────────────────────────────
    path('otp/request/', views.request_otp, name='request_otp'),
    path('otp/verify/', views.verify_otp, name='verify_otp'),

    # ── Affectations enseignant ───────────────────────────────────────────────
    path('enseignant/affectations/', views.enseignant_affectations, name='enseignant_affectations'),
    path('enseignant/affectations/niveau-filiere/', views.ajouter_niveau_filiere, name='ajouter_niveau_filiere'),
    path('enseignant/affectations/specialite/', views.ajouter_specialite, name='ajouter_specialite'),
    path('enseignant/affectations/matiere/', views.ajouter_matiere, name='ajouter_matiere'),
    path('enseignant/affectations/niveau-filiere/<int:pk>/', views.supprimer_niveau_filiere, name='supprimer_niveau_filiere'),
    path('enseignant/affectations/specialite/<int:pk>/', views.supprimer_specialite, name='supprimer_specialite'),
    path('enseignant/affectations/matiere/<int:pk>/', views.supprimer_matiere, name='supprimer_matiere'),

    # ── Dashboard Enseignant ──────────────────────────────────────────────────
    path('enseignant/profile/', views.enseignant_profile, name='enseignant_profile'),
    path('enseignant/niveaux/', views.enseignant_niveaux, name='enseignant_niveaux'),
    path('enseignant/filieres/', views.enseignant_filieres, name='enseignant_filieres'),
    path('enseignant/specialites/', views.enseignant_specialites, name='enseignant_specialites'),
    path('enseignant/matieres/', views.enseignant_matieres, name='enseignant_matieres'),
    path('enseignant/students/', views.students_list, name='students_list'),
    path('enseignant/notes/', views.notes_list, name='notes_list'),
    path('enseignant/notes/save/', views.save_notes, name='save_notes'),
    path('enseignant/resultats/', views.get_resultats, name='get_resultats'),
path('enseignant/planning/', views.mon_planning, name='mon_planning'),
    # ── Admin ─────────────────────────────────────────────────────────────────
    path('admin/profile/', admin_views.admin_profile, name='admin_profile'),
    path('admin/enseignants/', admin_views.get_enseignants, name='get_enseignants'),
    path('admin/enseignants/<int:enseignant_id>/validate/', admin_views.validate_enseignant, name='validate_enseignant'),
    path('admin/administrateurs/', admin_views.get_administrateurs, name='get_administrateurs'),
    path('admin/administrateurs/<int:admin_id>/approve/', admin_views.approve_administrateur, name='approve_administrateur'),

    # ── Périodes de saisie ────────────────────────────────────────────────────
    path('periodes/', admin_views.get_periodes, name='get_periodes'),
    path('periodes/creer/', admin_views.creer_periode, name='creer_periode'),
    path('periodes/tout-activer/', admin_views.tout_activer, name='tout_activer'),
    path('periodes/sections-ouvertes/', admin_views.sections_ouvertes, name='sections_ouvertes'),
    path('periodes/<int:periode_id>/toggle/', admin_views.toggle_periode, name='toggle_periode'),
    path('periodes/<int:periode_id>/supprimer/', admin_views.supprimer_periode, name='supprimer_periode'),


    path('planning/', admin_views.get_emplois_du_temps, name='get_emplois'),
    path('planning/creer/', admin_views.creer_creneau, name='creer_creneau'),
    path('planning/<int:creneau_id>/supprimer/', admin_views.supprimer_creneau, name='supprimer_creneau'),
    # ── ViewSets CRUD ─────────────────────────────────────────────────────────
    path('', include(router.urls)),
]
