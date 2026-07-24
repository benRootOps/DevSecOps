section .data
    msg db 'Hello, World!', 0xA ; La chaîne de message et le caractère de nouvelle ligne (0xA en ASCII)
    len equ $ - msg          ; Calcule la longueur du message

section .text
    global _start            ; Définit le point d'entrée du programme

_start:
    ; Appel système pour écrire (sys_write) sur la sortie standard (stdout)
    mov eax, 4               ; Numéro de l'appel système (write)
    mov ebx, 1               ; Descripteur de fichier 1 (stdout)
    mov ecx, msg             ; Pointeur vers le message
    mov edx, len             ; Longueur du message
    int 0x80                 ; Appelle le noyau

    ; Appel système pour quitter (sys_exit)
    mov eax, 1               ; Numéro de l'appel système (exit)
    xor ebx, ebx             ; Code de sortie 0 (succès)
    int 0x80                 ; Appelle le noyau