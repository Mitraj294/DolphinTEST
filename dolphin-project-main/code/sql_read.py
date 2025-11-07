import os
import json
from sqlalchemy import create_engine, MetaData, Table, select

# Point to the database which already contains the `input` and `results` tables.
engine = create_engine("mysql+pymysql://dolphin123:dolphin123@localhost/dolphin_clean")
metadata = MetaData()
input_table = Table("input", metadata, autoload_with=engine)

def get_user_words(email):
    with engine.connect() as conn:
        stmt = select(input_table.c.self_words, input_table.c.concept_words).where(input_table.c.email == email)
        row = conn.execute(stmt).fetchone()
        if row:
            self_words = row.self_words
            concept_words = row.concept_words

            base_dir = os.path.dirname(os.path.abspath(__file__))
            out_path = os.path.join(base_dir, "test_input.txt")

            print(f"Writing output to: {out_path}")

            with open(out_path, "w") as f:
                f.write("-" + email + "\n")
                for w in self_words:
                    f.write(w + "\n")
                f.write("$\n")
                for w in concept_words:
                    f.write(w + "\n")

            return self_words, concept_words

        return [], []
    
if __name__ == "__main__":
    import sys
    if len(sys.argv) < 2:
        print("Usage: python3 sql_read.py <email>")
        sys.exit(1)

    email = sys.argv[1]
    get_user_words(email)
