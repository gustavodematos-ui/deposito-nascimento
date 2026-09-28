datasource db {
  provider = "postgresql"
  url      = env("DATABASE_URL")
}

generator client {
  provider = "prisma-client-js"
}

enum Role {
  ADMIN
  USER
}

enum OrderStatus {
  PENDENTE
  APROVADO
  EM_TRANSITO
  ENTREGUE
  CANCELADO
}

enum ReposicaoStatus {
  AGUARDANDO_ENVIO
  APROVADO
  CONCLUIDO
  CANCELADO
}

model Usuario {
  id        String   @id @default(uuid())
  nome      String
  email     String   @unique
  senha     String
  role      Role     @default(USER)
  pedidos   Pedido[]
  createdAt DateTime @default(now())
}

model Produto {
  id              String            @id @default(uuid())
  codigo          String            @unique
  nome            String
  categoria       String
  preco           Decimal           @db.Decimal(10, 2)
  garantia        String
  estoque         Estoque?
  itensPedido     ItemPedido[]
  itensReposicao  ItemReposicao[]
  createdAt       DateTime          @default(now())
}

model Estoque {
  id           String   @id @default(uuid())
  produtoId    String   @unique
  produto      Produto  @relation(fields: [produtoId], references: [id])
  localizacao  String
  quantidade   Int
  minimoAlerta Int      @default(10)
  updatedAt    DateTime @updatedAt
}

model FormaPagamento {
  id             String   @id @default(uuid())
  metodo         String   @unique
  taxaOperacao   Decimal  @db.Decimal(5, 2)
  prazoLiberacao String
  ativo          Boolean  @default(true)
  pedidos        Pedido[]
}

model Pedido {
  id               String         @id @default(uuid())
  numero           Int            @default(autoincrement())
  usuarioId        String
  usuario          Usuario        @relation(fields: [usuarioId], references: [id])
  formaPagamentoId String
  formaPagamento   FormaPagamento @relation(fields: [formaPagamentoId], references: [id])
  valorTotal       Decimal        @db.Decimal(10, 2)
  status           OrderStatus    @default(PENDENTE)
  itens            ItemPedido[]
  createdAt        DateTime       @default(now())
}

model ItemPedido {
  id            String  @id @default(uuid())
  pedidoId      String
  pedido        Pedido  @relation(fields: [pedidoId], references: [id])
  produtoId     String
  produto       Produto @relation(fields: [produtoId], references: [id])
  quantidade    Int
  precoUnitario Decimal @db.Decimal(10, 2)
}

model Reposicao {
  id                 String          @id @default(uuid())
  codigo             String          @unique
  fornecedor         String
  precoTotalEstimado Decimal         @db.Decimal(10, 2)
  status             ReposicaoStatus @default(AGUARDANDO_ENVIO)
  itens              ItemReposicao[]
  createdAt          DateTime        @default(now())
}

model ItemReposicao {
  id          String    @id @default(uuid())
  reposicaoId String
  reposicao   Reposicao @relation(fields: [reposicaoId], references: [id])
  produtoId   String
  produto     Produto   @relation(fields: [produtoId], references: [id])
  quantidade  Int
}