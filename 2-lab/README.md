# Laboratório 2: Docker com PHP + MySQL

Este projeto demonstra a criação e execução de uma aplicação web PHP conectada a um banco de dados MySQL, utilizando containers Docker em rede dedicada e um script de automação (`script.sh`) para facilitar o processo de build e run.

## Como Usar

> [!NOTE]
> **Pré-requisitos:**
> - Docker instalado e em execução.
> - Permissão para executar shell script.
> - Arquivo `.env` configurado (use `.env.example` como base).

### Configuração

Crie o arquivo `.env` a partir do exemplo:

```bash
cp .env.example .env
```

Edite o `.env` com as suas credenciais:

```env
MYSQL_ROOT_PASSWORD=sua_senha
MYSQL_DATABASE=tasks_db
DB_USER=root
DB_PASS=sua_senha
```

### Execução

```bash
cd 2-lab
chmod +x script.sh
./script.sh
```

O script irá automaticamente:
- Criar a rede Docker dedicada para comunicação entre containers.
- Criar o volume Docker para persistência dos dados do MySQL.
- Iniciar o container MySQL com o banco de dados inicializado via `init.sql`.
- Construir a imagem Docker da aplicação PHP.
- Iniciar o container PHP conectado ao MySQL.
- Exibir o endereço de acesso.

Após a execução, a aplicação estará disponível em [http://localhost:4000](http://localhost:4000).

## Comandos Docker no Script

O `script.sh` automatiza os seguintes comandos:

### Docker Network Create

| Parâmetro | Descrição | Motivo |
| :--- | :--- | :--- |
| `cp01-lab2-net` | Nome da rede criada. | Isola os containers em uma rede privada para comunicação interna segura. |

### Docker Volume Create

| Parâmetro | Descrição | Motivo |
| :--- | :--- | :--- |
| `cp01-lab2-mysql-data` | Nome do volume criado. | Persiste os dados do MySQL mesmo após o container ser removido. |

### Docker Run (MySQL)

| Parâmetro | Descrição | Motivo |
| :--- | :--- | :--- |
| `-d` | **Detached Mode**: executa o container em segundo plano. | Libera o terminal para outros comandos enquanto o container roda. |
| `--name cp01-lab2-mysql` | Define um nome para o container. | Facilita o gerenciamento e referência pelo container PHP via `DB_HOST`. |
| `--network cp01-lab2-net` | Conecta o container à rede dedicada. | Permite comunicação com o container PHP pelo nome do container. |
| `-e MYSQL_ROOT_PASSWORD` | Define a senha do root do MySQL. | Autenticação do banco de dados (carregada do `.env`). |
| `-e MYSQL_DATABASE` | Define o banco de dados a ser criado. | Cria automaticamente o banco na inicialização (carregado do `.env`). |
| `-v (volume)` | Monta o volume de dados do MySQL. | Persiste os dados do banco entre reinicializações do container. |
| `-v init.sql` | Monta o script SQL de inicialização. | Executa automaticamente na primeira inicialização para criar tabelas. |

### Docker Build (PHP)

| Parâmetro | Descrição | Motivo |
| :--- | :--- | :--- |
| `-t cp01-lab2-php:v1.0` | Define a "tag" (nome e versão) da imagem. | Para identificar a imagem facilmente. |
| `.` | Define o contexto do build (o diretório atual). | O Docker usará os arquivos do diretório (`Dockerfile`, etc.) para construir a imagem. |

### Docker Run (PHP)

| Parâmetro | Descrição | Motivo |
| :--- | :--- | :--- |
| `-d` | **Detached Mode**: executa o container em segundo plano. | Libera o terminal para outros comandos enquanto o container roda. |
| `--name cp01-lab2-php` | Define um nome para o container. | Facilita o gerenciamento (parar, remover, ver logs) usando um nome fixo. |
| `--network cp01-lab2-net` | Conecta o container à rede dedicada. | Permite comunicação com o container MySQL pelo nome do container. |
| `-p 4000:80` | Mapeia a porta 4000 do host para a 80 do container. | Permite acessar o Apache (que roda na porta 80 do container) através de `localhost:4000`. |
| `-e DB_HOST` | Define o host do banco de dados. | Aponta para o container MySQL pelo nome (carregado dinamicamente). |
| `-e DB_NAME` | Define o nome do banco de dados. | Informa à aplicação PHP qual banco usar (carregado do `.env`). |
| `-e DB_USER` | Define o usuário do banco de dados. | Autenticação da aplicação no MySQL (carregado do `.env`). |
| `-e DB_PASS` | Define a senha do banco de dados. | Autenticação da aplicação no MySQL (carregado do `.env`). |
| `-v index.php` | Monta o arquivo PHP no container. | Para refletir alterações no `index.php` local imediatamente, sem precisar reconstruir a imagem. |

## Dockerfile

O `Dockerfile` utiliza a imagem base `php:8.2-apache`.
- `RUN docker-php-ext-install pdo pdo_mysql`: Instala as extensões PDO necessárias para a conexão com o MySQL.
- `EXPOSE 80`: Informa que o container expõe a porta 80 (Apache).

## Banco de Dados

O arquivo `init.sql` é executado automaticamente na primeira inicialização do container MySQL e cria a tabela `tasks` no banco `tasks_db`:

| Coluna | Tipo | Descrição |
| :--- | :--- | :--- |
| `id` | INT AUTO_INCREMENT | Chave primária. |
| `title` | VARCHAR(255) | Título da tarefa. |
| `description` | TEXT | Descrição detalhada da tarefa. |
| `status` | ENUM('pending', 'done') | Status da tarefa (padrão: `pending`). |
| `created_at` | TIMESTAMP | Data e hora de criação (preenchida automaticamente). |
