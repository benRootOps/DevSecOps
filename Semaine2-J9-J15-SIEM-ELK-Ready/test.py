from collections import Counter

nbre=[1,6,1,1,3,2,3,8,9,4]

compteur=Counter()

for i in nbre:
    compteur[i]+=1
    count=compteur[i]
    print("count de ", i, "est ", count)

print("boss", compteur.most_common(1))

print("valeur compteur",compteur)