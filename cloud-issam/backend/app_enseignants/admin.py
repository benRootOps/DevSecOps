from django.contrib import admin
from django.contrib.auth.admin import UserAdmin
from django.utils import timezone
from .models import (
    CustomUser, Enseignant, Administrateur, Niveau, Filiere, Specialite,
    Matiere, Etudiant, Note, EnseignantNiveau,
    EnseignantSpecialite, EnseignantMatiere, OTPVerification, PeriodeSaisie
)


# ============ CustomUser ============
@admin.register(CustomUser)
class CustomUserAdmin(UserAdmin):
    list_display = ['username', 'email', 'role', 'is_active', 'is_staff', 'date_joined']
    list_filter = ['role', 'is_active', 'is_staff', 'date_joined']
    search_fields = ['username', 'email', 'first_name', 'last_name']
    ordering = ['-date_joined']

    fieldsets = UserAdmin.fieldsets + (
        ('Informations supplémentaires', {'fields': ('role',)}),
    )
    add_fieldsets = UserAdmin.add_fieldsets + (
        ('Informations supplémentaires', {'fields': ('role',)}),
    )


# ============ Enseignant ============
@admin.register(Enseignant)
class EnseignantAdmin(admin.ModelAdmin):
    list_display = ['id', 'nom', 'prenom', 'get_email', 'get_username', 'is_verified', 'created_at']
    search_fields = ['nom', 'prenom', 'user__email', 'user__username']
    list_filter = ['is_verified', 'created_at']
    ordering = ['-created_at']

    fieldsets = (
        ('Compte utilisateur', {'fields': ('user',)}),
        ('Informations personnelles', {'fields': ('nom', 'prenom', 'date_naissance', 'lieu_naissance')}),
        ('Vérification', {'fields': ('is_verified',)}),
        ('Dates', {'fields': ('created_at', 'updated_at'), 'classes': ('collapse',)}),
    )
    readonly_fields = ['created_at', 'updated_at']

    def get_email(self, obj):
        return obj.user.email if obj.user else '-'
    get_email.short_description = 'Email'
    get_email.admin_order_field = 'user__email'

    def get_username(self, obj):
        return obj.user.username if obj.user else '-'
    get_username.short_description = 'Username'
    get_username.admin_order_field = 'user__username'


# ============ Administrateur ============
@admin.register(Administrateur)
class AdministrateurAdmin(admin.ModelAdmin):
    list_display = ['id', 'nom', 'prenom', 'get_email', 'get_username', 'is_approved', 'created_at']
    search_fields = ['nom', 'prenom', 'user__email', 'user__username']
    list_filter = ['is_approved', 'created_at']
    ordering = ['-created_at']

    fieldsets = (
        ('Compte utilisateur', {'fields': ('user',)}),
        ('Informations personnelles', {'fields': ('nom', 'prenom', 'date_naissance', 'lieu_naissance')}),
        ('Validation', {
            'fields': ('is_approved',),
            'description': 'Approuver ce compte pour lui donner accès au système'
        }),
        ('Dates', {'fields': ('created_at', 'updated_at'), 'classes': ('collapse',)}),
    )
    readonly_fields = ['created_at', 'updated_at']
    actions = ['approve_admins', 'reject_admins']

    def get_email(self, obj):
        return obj.user.email if obj.user else '-'
    get_email.short_description = 'Email'
    get_email.admin_order_field = 'user__email'

    def get_username(self, obj):
        return obj.user.username if obj.user else '-'
    get_username.short_description = 'Username'
    get_username.admin_order_field = 'user__username'

    def approve_admins(self, request, queryset):
        count = 0
        for admin in queryset:
            if admin.user:
                admin.is_approved = True
                admin.user.is_staff = True
                admin.save()
                admin.user.save()
                count += 1
        self.message_user(request, f'{count} administrateur(s) approuvé(s).')
    approve_admins.short_description = "✅ Approuver les administrateurs sélectionnés"

    def reject_admins(self, request, queryset):
        count = 0
        for admin in queryset:
            if admin.user:
                admin.is_approved = False
                admin.user.is_staff = False
                admin.save()
                admin.user.save()
                count += 1
        self.message_user(request, f'{count} administrateur(s) rejeté(s).')
    reject_admins.short_description = "❌ Rejeter les administrateurs sélectionnés"


# ============ Niveau ============
@admin.register(Niveau)
class NiveauAdmin(admin.ModelAdmin):
    list_display = ['id', 'nom', 'ordre', 'created_at']
    search_fields = ['nom']
    list_filter = ['created_at']
    ordering = ['ordre', 'nom']
    fieldsets = (
        ('Informations', {'fields': ('nom', 'ordre', 'description')}),
    )


# ============ Filière ============
@admin.register(Filiere)
class FiliereAdmin(admin.ModelAdmin):
    list_display = ['id', 'nom', 'code', 'created_at']
    search_fields = ['nom', 'code']
    list_filter = ['created_at']
    ordering = ['nom']
    fieldsets = (
        ('Informations', {'fields': ('nom', 'code', 'description')}),
    )


# ============ Spécialité ============
@admin.register(Specialite)
class SpecialiteAdmin(admin.ModelAdmin):
    list_display = ['id', 'nom', 'filiere', 'created_at']
    search_fields = ['nom', 'filiere__nom']
    list_filter = ['filiere', 'created_at']
    ordering = ['filiere', 'nom']
    fieldsets = (
        ('Informations', {'fields': ('nom', 'filiere', 'description')}),
    )


# ============ Matière ============
@admin.register(Matiere)
class MatiereAdmin(admin.ModelAdmin):
    list_display = ['id', 'nom', 'code', 'niveau', 'filiere', 'coefficient', 'created_at']
    search_fields = ['nom', 'code']
    list_filter = ['niveau', 'filiere', 'created_at']
    filter_horizontal = ['specialites']
    ordering = ['niveau', 'filiere', 'nom']
    fieldsets = (
        ('Informations générales', {'fields': ('nom', 'code', 'description', 'coefficient')}),
        ('Relations académiques', {'fields': ('niveau', 'filiere', 'specialites')}),
    )


# ============ Étudiant ============
@admin.register(Etudiant)
class EtudiantAdmin(admin.ModelAdmin):
    list_display = ['id', 'matricule', 'nom', 'prenom', 'niveau', 'filiere', 'specialite', 'email']
    search_fields = ['matricule', 'nom', 'prenom', 'email']
    list_filter = ['niveau', 'filiere', 'specialite', 'created_at']
    ordering = ['nom', 'prenom']
    fieldsets = (
        ('Informations personnelles', {'fields': ('matricule', 'nom', 'prenom', 'date_naissance', 'lieu_naissance')}),
        ('Contact', {'fields': ('email', 'telephone')}),
        ('Informations académiques', {'fields': ('niveau', 'filiere', 'specialite')}),
    )


# ============ Note ============
@admin.register(Note)
class NoteAdmin(admin.ModelAdmin):
    # CORRECTION : note_cc/note_sn → note_cc1/note_sn1/note_cc2/note_sn2, semestre supprimé
    list_display = [
        'id', 'etudiant', 'matiere', 'enseignant',
        'note_cc1', 'note_sn1', 'note_cc2', 'note_sn2',
        'moyenne_s1_display', 'moyenne_s2_display',
        'annee_academique', 'updated_at'
    ]
    search_fields = ['etudiant__nom', 'etudiant__prenom', 'matiere__nom', 'enseignant__nom']
    list_filter = ['matiere__niveau', 'matiere__filiere', 'annee_academique', 'created_at']
    ordering = ['-updated_at']

    fieldsets = (
        ('Informations', {'fields': ('etudiant', 'matiere', 'enseignant')}),
        ('Semestre 1', {'fields': ('note_cc1', 'note_sn1')}),
        ('Semestre 2', {'fields': ('note_cc2', 'note_sn2')}),
        ('Période', {'fields': ('annee_academique',)}),
        ('Dates', {'fields': ('created_at', 'updated_at'), 'classes': ('collapse',)}),
    )
    readonly_fields = ['created_at', 'updated_at']

    def moyenne_s1_display(self, obj):
        return f"{obj.moyenne_s1:.2f}" if obj.moyenne_s1 is not None else "-"
    moyenne_s1_display.short_description = 'Moy. S1'

    def moyenne_s2_display(self, obj):
        return f"{obj.moyenne_s2:.2f}" if obj.moyenne_s2 is not None else "-"
    moyenne_s2_display.short_description = 'Moy. S2'


# ============ Période de Saisie ============
@admin.register(PeriodeSaisie)
class PeriodeSaisieAdmin(admin.ModelAdmin):
    list_display = [
        'id', 'section', 'annee_academique',
        'date_debut', 'date_fin',
        'is_active', 'est_en_cours_display', 'ouvert_par', 'created_at'
    ]
    list_filter = ['section', 'is_active', 'annee_academique']
    ordering = ['-date_debut']
    actions = ['activer_periodes', 'desactiver_periodes']

    fieldsets = (
        ('Configuration', {'fields': ('section', 'annee_academique')}),
        ('Période', {'fields': ('date_debut', 'date_fin')}),
        ('Statut', {'fields': ('is_active', 'ouvert_par')}),
        ('Dates', {'fields': ('created_at', 'updated_at'), 'classes': ('collapse',)}),
    )
    readonly_fields = ['created_at', 'updated_at']

    def est_en_cours_display(self, obj):
        return obj.est_en_cours
    est_en_cours_display.boolean = True
    est_en_cours_display.short_description = 'En cours'

    def activer_periodes(self, request, queryset):
        count = queryset.update(is_active=True)
        self.message_user(request, f'{count} période(s) activée(s).')
    activer_periodes.short_description = "✅ Activer les périodes sélectionnées"

    def desactiver_periodes(self, request, queryset):
        count = queryset.update(is_active=False)
        self.message_user(request, f'{count} période(s) désactivée(s).')
    desactiver_periodes.short_description = "🔒 Désactiver les périodes sélectionnées"


# ============ Relations Enseignant ============
@admin.register(EnseignantNiveau)
class EnseignantNiveauAdmin(admin.ModelAdmin):
    list_display = ['id', 'enseignant', 'niveau', 'filiere', 'created_at']
    search_fields = ['enseignant__nom', 'enseignant__prenom', 'niveau__nom', 'filiere__nom']
    list_filter = ['niveau', 'filiere', 'created_at']
    ordering = ['-created_at']
    fieldsets = (
        ('Relations', {'fields': ('enseignant', 'niveau', 'filiere')}),
    )


@admin.register(EnseignantSpecialite)
class EnseignantSpecialiteAdmin(admin.ModelAdmin):
    list_display = ['id', 'enseignant', 'specialite', 'created_at']
    search_fields = ['enseignant__nom', 'enseignant__prenom', 'specialite__nom']
    list_filter = ['specialite__filiere', 'created_at']
    ordering = ['-created_at']
    fieldsets = (
        ('Relations', {'fields': ('enseignant', 'specialite')}),
    )


@admin.register(EnseignantMatiere)
class EnseignantMatiereAdmin(admin.ModelAdmin):
    list_display = ['id', 'enseignant', 'matiere', 'created_at']
    search_fields = ['enseignant__nom', 'enseignant__prenom', 'matiere__nom']
    list_filter = ['matiere__niveau', 'matiere__filiere', 'created_at']
    ordering = ['-created_at']
    fieldsets = (
        ('Relations', {'fields': ('enseignant', 'matiere')}),
    )


# ============ OTP ============
@admin.register(OTPVerification)
class OTPVerificationAdmin(admin.ModelAdmin):
    list_display = ['id', 'email', 'code', 'is_used', 'is_expired', 'expires_at', 'created_at']
    search_fields = ['email', 'code']
    list_filter = ['is_used', 'created_at']
    ordering = ['-created_at']
    readonly_fields = ['created_at']

    fieldsets = (
        ('Informations OTP', {'fields': ('email', 'code', 'is_used', 'expires_at')}),
        ('Dates', {'fields': ('created_at',)}),
    )

    def is_expired(self, obj):
        return obj.expires_at < timezone.now()
    is_expired.boolean = True
    is_expired.short_description = 'Expiré'


# Personnalisation du site admin
admin.site.site_header = "🎓 CLOUD ISSAM - Administration"
admin.site.site_title = "CLOUD ISSAM Admin"
admin.site.index_title = "Gestion de la plateforme de notes"
