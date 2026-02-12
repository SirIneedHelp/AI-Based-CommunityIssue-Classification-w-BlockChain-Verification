import { readFileSync } from "fs";
import { ethers } from "ethers";

// Ganache RPC
const RPC_URL = "http://127.0.0.1:7545";

// ✅ Use env var PRIVATE_KEY if provided, otherwise fallback to default deployer key
const DEFAULT_PRIVATE_KEY =
  "0x64880b7eb8c4f56d9333030a6c83e592c6b5ff59a167e7f209ed137004dc385e";
const PRIVATE_KEY = process.env.PRIVATE_KEY || DEFAULT_PRIVATE_KEY;

// Your deployed contract address
const CONTRACT_ADDRESS = "0x0bB793123632b8F377FeB5DC160B63F42D45ce58";

// ABI from Hardhat artifact
const artifactPath = "./artifacts/contracts/Verification.sol/Verification.json";

function usage() {
  console.log(
    'Usage:\n  node verify.mjs <reportId> <dataHashHex> <category> <modelVersion>\n\n' +
      'Example:\n  node verify.mjs 12 0x' +
      "a".repeat(64) +
      ' "Garbage" "v1"\n\n' +
      "Notes:\n" +
      "- reportId must be a number\n" +
      "- dataHashHex must be 0x + 64 hex chars (bytes32)\n" +
      "- Optional: set PRIVATE_KEY env var to test non-owner\n" +
      '  Example: PRIVATE_KEY="0x...." node verify.mjs ...\n'
  );
}

function isBytes32(hex) {
  return /^0x[0-9a-fA-F]{64}$/.test(hex);
}

async function main() {
  const args = process.argv.slice(2);
  if (args.length < 4) {
    usage();
    process.exit(1);
  }

  const reportId = Number(args[0]);
  const dataHashHex = args[1];
  const category = args[2];
  const modelVersion = args[3];

  if (!Number.isInteger(reportId) || reportId < 0) {
    console.error("❌ reportId must be a non-negative integer");
    process.exit(1);
  }

  if (!isBytes32(dataHashHex)) {
    console.error("❌ dataHashHex must be bytes32: 0x + 64 hex chars");
    process.exit(1);
  }

  const artifact = JSON.parse(readFileSync(artifactPath, "utf8"));
  const abi = artifact.abi;

  const provider = new ethers.JsonRpcProvider(RPC_URL);
  const wallet = new ethers.Wallet(PRIVATE_KEY, provider);

  const contract = new ethers.Contract(CONTRACT_ADDRESS, abi, wallet);

  console.log("Caller address:", await wallet.getAddress());
  console.log("Contract owner:", await contract.owner());

  console.log("Sending transaction...");
  const tx = await contract.recordVerification(reportId, dataHashHex, category, modelVersion);

  console.log("⏳ tx hash:", tx.hash);
  const receipt = await tx.wait();
  console.log("✅ confirmed in block:", receipt.blockNumber);
}

main().catch((e) => {
  console.error("❌ verify failed:", e?.shortMessage || e?.message || e);
  process.exit(1);
});
